<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyOperationLog;
use App\Models\Inventory;
use App\Models\InventoryDailySnapshot;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    /**
     * 进货单列表。
     *
     * GET /api/purchase-orders?date=&status=
     */
    public function index(Request $request): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $query = PurchaseOrder::with(['supplier:id,name', 'items.product:id,name,unit'])
            ->where('store_id', $storeId);

        if ($request->filled('date')) {
            $query->whereDate('actual_delivery_date', $request->date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($orders);
    }

    /**
     * 进货单详情（含明细）。
     *
     * GET /api/purchase-orders/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $order = PurchaseOrder::with(['supplier:id,name', 'items.product:id,name,unit', 'creator:id,name'])
            ->where('store_id', $storeId)
            ->findOrFail($id);

        return response()->json(['data' => $order]);
    }

    /**
     * 创建进货单并自动确认收货，更新库存。
     *
     * 适用于 skill 图片录入场景：Claude 解析图片 → 用户确认 → 调此接口一次完成。
     *
     * POST /api/purchase-orders
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_name' => 'required|string|max:100',
            'items.*.ordered_qty' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $deliveryDate = $data['date'];
        $now = now();

        $result = DB::transaction(function () use ($data, $storeId, $deliveryDate, $now, $request) {
            $totalAmount = collect($data['items'])->sum(function ($item) {
                return round((float) ($item['unit_price'] ?? 0) * (float) $item['ordered_qty'], 2);
            });

            $order = PurchaseOrder::create([
                'store_id' => $storeId,
                'supplier_id' => $data['supplier_id'] ?? null,
                'order_no' => PurchaseOrder::generateOrderNo($storeId),
                'order_type' => 1,
                'status' => 5, // 已收货
                'actual_delivery_date' => $deliveryDate,
                'expected_delivery_date' => $deliveryDate,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? '图片录入',
                'created_by' => $request->user()->id,
            ]);

            $itemResults = [];

            foreach ($data['items'] as $itemData) {
                $product = Product::findOrCreateByName($itemData['product_name']);
                $orderedQty = (float) $itemData['ordered_qty'];
                $unitPrice = (float) ($itemData['unit_price'] ?? 0);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $product->id,
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'ordered_qty' => $orderedQty,
                    'received_qty' => $orderedQty,
                    'unit_price' => $unitPrice,
                    'total_price' => round($unitPrice * $orderedQty, 2),
                ]);

                // 更新库存
                $inventory = Inventory::firstOrCreate(
                    ['store_id' => $storeId, 'product_id' => $product->id],
                    ['current_qty' => 0, 'available_qty' => 0, 'locked_qty' => 0],
                );

                $qtyBefore = (float) $inventory->current_qty;
                $qtyAfter = $qtyBefore + $orderedQty;

                InventoryTransaction::create([
                    'store_id' => $storeId,
                    'product_id' => $product->id,
                    'transaction_type' => 1, // 进货入库
                    'qty_change' => $orderedQty,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'unit_cost' => $unitPrice ?: null,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $order->id,
                    'operator_id' => $request->user()->id,
                    'notes' => "进货单 {$order->order_no}",
                    'created_at' => $now,
                ]);

                $inventory->update([
                    'current_qty' => $qtyAfter,
                    'available_qty' => $qtyAfter,
                    'last_in_at' => $now,
                ]);

                InventoryDailySnapshot::record(
                    storeId: $storeId,
                    productId: $product->id,
                    qtyBefore: $qtyBefore,
                    qtyChange: $orderedQty,
                    qtyAfter: $qtyAfter,
                    transactionType: 1,
                    date: $deliveryDate,
                    occurredAt: $now,
                );

                $itemResults[] = [
                    'product_name' => $product->name,
                    'received_qty' => $orderedQty,
                    'unit_price' => $unitPrice,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                ];
            }

            DailyOperationLog::write(
                storeId: $storeId,
                content: "进货收货: {$order->order_no} 共 ".count($data['items']).' 个SKU',
                intent: 'stock_in',
                source: 2,
                isOperational: true,
                operatorId: $request->user()->id,
                referenceType: 'purchase_order',
                referenceId: $order->id,
                occurredAt: $now,
            );

            return [
                'order_no' => $order->order_no,
                'id' => $order->id,
                'date' => $deliveryDate,
                'items' => $itemResults,
            ];
        });

        return response()->json([
            'message' => '进货单已创建并完成收货',
            'order_no' => $result['order_no'],
            'id' => $result['id'],
            'date' => $result['date'],
            'items' => $result['items'],
        ], 201);
    }
}
