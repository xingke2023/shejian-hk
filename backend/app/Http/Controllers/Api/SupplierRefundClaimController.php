<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DamageRecord;
use App\Models\SupplierRefundClaim;
use App\Models\SupplierRefundClaimItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierRefundClaimController extends Controller
{
    /**
     * 从损耗记录生成供应商退款申请单。
     *
     * POST /api/refund-claims
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'damage_record_ids' => 'required|array|min:1',
            'damage_record_ids.*' => 'required|integer|exists:damage_records,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        // 加载所有选中的损耗记录并校验
        $records = DamageRecord::with('product:id,name,unit')
            ->where('store_id', $storeId)
            ->whereIn('id', $data['damage_record_ids'])
            ->get();

        if ($records->count() !== count($data['damage_record_ids'])) {
            return response()->json(['message' => '部分损耗记录不存在或不属于本门店'], 422);
        }

        $mismatched = $records->filter(fn ($r) => $r->supplier_id && $r->supplier_id !== $data['supplier_id']);
        if ($mismatched->isNotEmpty()) {
            return response()->json([
                'message' => '部分损耗记录关联��不同供应商，请分开提交',
                'mismatch' => $mismatched->pluck('id'),
            ], 422);
        }

        $alreadySubmitted = $records->where('status', '>', 1);
        if ($alreadySubmitted->isNotEmpty()) {
            return response()->json([
                'message' => '部分损耗记录已提交申请，请勿重复提交',
                'ids' => $alreadySubmitted->pluck('id'),
            ], 422);
        }

        $claim = DB::transaction(function () use ($data, $storeId, $records, $request) {
            $totalQty = $records->sum(fn ($r) => (float) $r->qty);
            $totalAmount = $records->sum(fn ($r) => (float) ($r->total_claimed ?? 0));

            $claim = SupplierRefundClaim::create([
                'store_id' => $storeId,
                'supplier_id' => $data['supplier_id'],
                'claim_no' => SupplierRefundClaim::generateClaimNo(),
                'status' => 1,
                'total_items' => $records->count(),
                'total_qty' => $totalQty,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($records as $record) {
                SupplierRefundClaimItem::create([
                    'claim_id' => $claim->id,
                    'damage_record_id' => $record->id,
                    'product_id' => $record->product_id,
                    'product_name' => $record->product?->name ?? '',
                    'qty' => (float) $record->qty,
                    'unit_cost' => $record->unit_cost,
                    'claimed_amount' => (float) ($record->total_claimed ?? 0),
                    'purchase_order_id' => $record->purchaseOrderItem?->purchase_order_id,
                ]);
            }

            // 标记损耗记录已提交
            DamageRecord::whereIn('id', $records->pluck('id'))->update(['status' => 2]);

            return $claim;
        });

        return response()->json([
            'message' => '退款申请单已创建',
            'data' => $claim->load('supplier:id,name,contact_name,contact_phone', 'items'),
        ], 201);
    }

    /**
     * 申请单列表。
     *
     * GET /api/refund-claims?status=&supplier_id=
     */
    public function index(Request $request): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $query = SupplierRefundClaim::with('supplier:id,name')
            ->where('store_id', $storeId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $claims = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($claims);
    }

    /**
     * 申请单详情（含明细）。
     *
     * GET /api/refund-claims/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $claim = SupplierRefundClaim::with([
            'supplier:id,name,contact_name,contact_phone',
            'items.damageRecord.product:id,name,unit',
            'items.damageRecord' => fn ($q) => $q->select('id', 'reason', 'occurred_at', 'image_paths', 'notes'),
        ])
            ->where('store_id', $storeId)
            ->findOrFail($id);

        return response()->json(['data' => $claim]);
    }

    /**
     * 更新申请单状态。
     *
     * PUT /api/refund-claims/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|integer|in:1,2,3,4,5',
            'notes' => 'nullable|string|max:500',
        ]);

        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $claim = SupplierRefundClaim::where('store_id', $storeId)->findOrFail($id);

        $update = ['status' => $data['status']];

        if ($data['status'] === 2 && ! $claim->submitted_at) {
            $update['submitted_at'] = now();
        }

        if (in_array($data['status'], [4, 5]) && ! $claim->resolved_at) {
            $update['resolved_at'] = now();
        }

        if (isset($data['notes'])) {
            $update['notes'] = $data['notes'];
        }

        $claim->update($update);

        // 如果状态是已退款，同步更新对应损耗记录
        if ($data['status'] === 4) {
            $damageIds = $claim->items()->pluck('damage_record_id');
            DamageRecord::whereIn('id', $damageIds)->update(['status' => 3]);
        }

        return response()->json(['message' => '状态已更新', 'data' => $claim->fresh()->load('supplier:id,name')]);
    }
}
