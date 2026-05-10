<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyOperationLog;
use App\Models\DamageRecord;
use App\Models\Inventory;
use App\Models\InventoryDailySnapshot;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DamageController extends Controller
{
    /**
     * 录入损耗记录（扣库存 + 写快照 + 写日志 + 自动关联进货单/供应商）。
     *
     * POST /api/damage
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_name' => 'required|string|max:100',
            'qty' => 'required|numeric|min:0.001',
            'reason' => 'required|string|max:100',
            'notes' => 'nullable|string|max:500',
            'occurred_at' => 'nullable|date',
            'image_base64' => 'nullable|array|max:5',
            'image_base64.*' => 'nullable|string',
            'purchase_order_item_id' => 'nullable|integer|exists:purchase_order_items,id',
        ]);

        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $occurredAt = isset($data['occurred_at']) ? now()->parse($data['occurred_at']) : now();

        // 保存图片
        $imagePaths = $this->saveBase64Images($data['image_base64'] ?? [], $storeId, $occurredAt->toDateString());

        $record = DB::transaction(function () use ($data, $storeId, $occurredAt, $imagePaths, $request) {
            $product = Product::findOrCreateByName($data['product_name']);

            $inventory = Inventory::firstOrCreate(
                ['store_id' => $storeId, 'product_id' => $product->id],
                ['current_qty' => 0, 'available_qty' => 0, 'locked_qty' => 0],
            );

            $qtyBefore = (float) $inventory->current_qty;
            $qty = (float) $data['qty'];
            $qtyAfter = max(0, $qtyBefore - $qty);
            $qtyChange = -($qtyBefore - $qtyAfter);

            // 库存流水
            InventoryTransaction::create([
                'store_id' => $storeId,
                'product_id' => $product->id,
                'transaction_type' => 3, // 损耗报废
                'qty_change' => $qtyChange,
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyAfter,
                'operator_id' => $request->user()->id,
                'notes' => $data['reason'].($data['notes'] ? '：'.$data['notes'] : ''),
                'created_at' => $occurredAt,
            ]);

            $inventory->update([
                'current_qty' => $qtyAfter,
                'available_qty' => $qtyAfter,
                'last_out_at' => $occurredAt,
            ]);

            // 每日快照
            InventoryDailySnapshot::record(
                storeId: $storeId,
                productId: $product->id,
                qtyBefore: $qtyBefore,
                qtyChange: $qtyChange,
                qtyAfter: $qtyAfter,
                transactionType: 3,
                date: $occurredAt->toDateString(),
                occurredAt: $occurredAt,
            );

            // 自动关联进货单明细（找最近一笔已收货进货单）
            $poItemId = $data['purchase_order_item_id'] ?? null;
            $supplierId = null;
            $unitCost = null;

            if (! $poItemId) {
                $poItem = PurchaseOrderItem::whereHas('purchaseOrder', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId)->where('status', 5); // 已收货
                })
                    ->where('product_id', $product->id)
                    ->orderByDesc('id')
                    ->first();

                if ($poItem) {
                    $poItemId = $poItem->id;
                    $supplierId = $poItem->supplier_id ?? $poItem->purchaseOrder?->supplier_id;
                    $unitCost = $poItem->unit_price ? (float) $poItem->unit_price : null;
                }
            } else {
                $poItem = PurchaseOrderItem::find($poItemId);
                $supplierId = $poItem?->supplier_id ?? $poItem?->purchaseOrder?->supplier_id;
                $unitCost = $poItem?->unit_price ? (float) $poItem->unit_price : null;
            }

            $totalClaimed = $unitCost ? round($qty * $unitCost, 2) : null;

            // 操作日志
            DailyOperationLog::write(
                storeId: $storeId,
                content: "损耗登记: {$product->name} {$qty}{$product->unit} 原因:{$data['reason']}".($totalClaimed ? " 索赔金额¥{$totalClaimed}" : ''),
                intent: 'waste_report',
                source: 2,
                isOperational: true,
                productId: $product->id,
                qtyChange: $qtyChange,
                operatorId: $request->user()->id,
                occurredAt: $occurredAt,
            );

            return DamageRecord::create([
                'store_id' => $storeId,
                'product_id' => $product->id,
                'purchase_order_item_id' => $poItemId,
                'supplier_id' => $supplierId,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'total_claimed' => $totalClaimed,
                'reason' => $data['reason'],
                'image_paths' => $imagePaths ?: null,
                'status' => 1,
                'occurred_at' => $occurredAt,
                'operator_id' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return response()->json([
            'message' => '损耗记录已保存',
            'data' => $record->load('product:id,name,unit', 'supplier:id,name'),
        ], 201);
    }

    /**
     * 损耗列表。
     *
     * GET /api/damage?date=&product_id=&status=&supplier_id=
     */
    public function index(Request $request): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $query = DamageRecord::with(['product:id,name,unit', 'supplier:id,name', 'operator:id,name'])
            ->where('store_id', $storeId);

        if ($request->filled('date')) {
            $query->whereDate('occurred_at', $request->date);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $records = $query->orderByDesc('occurred_at')->paginate(20);

        return response()->json($records);
    }

    /**
     * 损耗统计汇总（按商品 + 按供应商）。
     *
     * GET /api/damage/stats?from=&to=
     */
    public function stats(Request $request): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $from = $request->input('from', today()->startOfMonth()->toDateString());
        $to = $request->input('to', today()->toDateString());

        $base = DamageRecord::where('store_id', $storeId)
            ->whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59']);

        $totalQty = (float) (clone $base)->sum('qty');
        $totalClaimed = (float) (clone $base)->sum('total_claimed');

        $byProduct = (clone $base)
            ->select('product_id', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(total_claimed) as total_claimed'), DB::raw('COUNT(*) as records_count'))
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->with('product:id,name,unit')
            ->get()
            ->map(fn ($r) => [
                'product_id' => $r->product_id,
                'product_name' => $r->product?->name,
                'unit' => $r->product?->unit,
                'total_qty' => (float) $r->total_qty,
                'total_claimed' => (float) $r->total_claimed,
                'records_count' => (int) $r->records_count,
            ]);

        $bySupplier = (clone $base)
            ->whereNotNull('supplier_id')
            ->select('supplier_id', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(total_claimed) as total_claimed'), DB::raw('COUNT(*) as records_count'))
            ->groupBy('supplier_id')
            ->orderByDesc('total_claimed')
            ->with('supplier:id,name,contact_name,contact_phone')
            ->get()
            ->map(fn ($r) => [
                'supplier_id' => $r->supplier_id,
                'supplier_name' => $r->supplier?->name,
                'contact' => $r->supplier?->contact_name,
                'contact_phone' => $r->supplier?->contact_phone,
                'total_qty' => (float) $r->total_qty,
                'total_claimed' => (float) $r->total_claimed,
                'records_count' => (int) $r->records_count,
            ]);

        // 可提交退款的损耗（status=1，有供应商）
        $pendingClaims = (clone $base)
            ->where('status', 1)
            ->whereNotNull('supplier_id')
            ->count();

        return response()->json([
            'data' => [
                'from' => $from,
                'to' => $to,
                'total_qty' => $totalQty,
                'total_claimed' => $totalClaimed,
                'pending_claims_count' => $pendingClaims,
                'by_product' => $byProduct,
                'by_supplier' => $bySupplier,
            ],
        ]);
    }

    /**
     * 追加上传图片到已有损耗记录。
     *
     * POST /api/damage/{id}/images  (multipart: images[])
     */
    public function uploadImages(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'images' => 'required|array|max:5',
            'images.*' => 'required|image|max:5120',
        ]);

        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $record = DamageRecord::where('store_id', $storeId)->findOrFail($id);

        $newPaths = [];
        foreach ($request->file('images') as $file) {
            $path = $file->store("damage/{$storeId}/".now()->toDateString(), 'public');
            $newPaths[] = '/storage/'.$path;
        }

        $existing = $record->image_paths ?? [];
        $record->update(['image_paths' => array_merge($existing, $newPaths)]);

        return response()->json(['message' => '图片上传成功', 'image_paths' => $record->fresh()->image_paths]);
    }

    /** 将 base64 图片保存到 storage，返回 URL 数组 */
    private function saveBase64Images(array $base64List, int $storeId, string $date): array
    {
        $urls = [];
        foreach ($base64List as $b64) {
            if (! $b64) {
                continue;
            }

            // 去掉 data:image/...;base64, 前缀
            $raw = preg_replace('/^data:image\/\w+;base64,/', '', $b64);
            $binary = base64_decode($raw, strict: true);
            if (! $binary) {
                continue;
            }

            $filename = Str::uuid().'.jpg';
            $path = "damage/{$storeId}/{$date}/{$filename}";
            Storage::disk('public')->put($path, $binary);
            $urls[] = '/storage/'.$path;
        }

        return $urls;
    }
}
