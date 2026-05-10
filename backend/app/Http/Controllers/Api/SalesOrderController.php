<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyOperationLog;
use App\Models\Inventory;
use App\Models\InventoryDailySnapshot;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\SalesDailySummary;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesOrderController extends Controller
{
    /**
     * 零售流水列表（分页，支持日期/收银员筛选）。
     */
    public function index(Request $request): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $query = SalesOrder::with(['cashier:id,name', 'items.product:id,name,unit'])
            ->where('store_id', $storeId);

        if ($request->filled('date')) {
            $query->whereDate('sold_at', $request->date);
        }

        if ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->cashier_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderByDesc('sold_at')->paginate(20);

        return response()->json($orders);
    }

    /**
     * 新建销售单（含明细，自动扣减库存）。
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|integer|in:1,2,3,4,5',
            'cashier_id' => 'nullable|integer|exists:users,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $order = DB::transaction(function () use ($data, $storeId) {
            $totalAmount = collect($data['items'])->sum(function ($item) {
                $discount = $item['discount_amount'] ?? 0;

                return round($item['qty'] * $item['unit_price'] - $discount, 2);
            });

            $orderDiscount = $data['discount_amount'] ?? 0;

            $order = SalesOrder::create([
                'store_id' => $storeId,
                'order_no' => 'SO-'.date('Ymd').'-'.strtoupper(Str::random(6)),
                'cashier_id' => $data['cashier_id'] ?? null,
                'total_amount' => $totalAmount,
                'discount_amount' => $orderDiscount,
                'paid_amount' => $data['paid_amount'],
                'payment_method' => $data['payment_method'],
                'status' => 1,
                'sold_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $now = now();

            foreach ($data['items'] as $itemData) {
                $itemDiscount = $itemData['discount_amount'] ?? 0;
                $subtotal = round($itemData['qty'] * $itemData['unit_price'] - $itemDiscount, 2);

                $order->items()->create([
                    'product_id' => $itemData['product_id'],
                    'qty' => $itemData['qty'],
                    'unit_price' => $itemData['unit_price'],
                    'discount_amount' => $itemDiscount,
                    'subtotal' => $subtotal,
                    'cost_price' => null,
                ]);

                // 扣减库存
                $inventory = Inventory::firstOrCreate(
                    ['store_id' => $storeId, 'product_id' => $itemData['product_id']],
                    ['current_qty' => 0, 'available_qty' => 0, 'locked_qty' => 0],
                );

                $qtyBefore = (float) $inventory->current_qty;
                $qtyChange = -(float) $itemData['qty'];
                $qtyAfter = $qtyBefore + $qtyChange;

                InventoryTransaction::create([
                    'store_id' => $storeId,
                    'product_id' => $itemData['product_id'],
                    'transaction_type' => 2, // 销售出库
                    'qty_change' => $qtyChange,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'unit_cost' => null,
                    'reference_type' => 'sales_order',
                    'reference_id' => $order->id,
                    'notes' => "零售 {$order->order_no}",
                    'created_at' => $now,
                ]);

                $inventory->update([
                    'current_qty' => $qtyAfter,
                    'available_qty' => $qtyAfter,
                    'last_out_at' => $now,
                    'last_sold_at' => $now,
                ]);

                InventoryDailySnapshot::record(
                    storeId: $storeId,
                    productId: $itemData['product_id'],
                    qtyBefore: $qtyBefore,
                    qtyChange: $qtyChange,
                    qtyAfter: $qtyAfter,
                    transactionType: 2,
                    date: today()->toDateString(),
                    occurredAt: $now,
                );

                // 实时累加当日销售汇总（来源：POS）
                SalesDailySummary::accumulate(
                    storeId: $storeId,
                    productId: $itemData['product_id'],
                    date: today()->toDateString(),
                    qty: (float) $itemData['qty'],
                    amount: $subtotal,
                    source: 'pos',
                );
            }

            return $order;
        });

        // POS 销售写操作日志
        foreach ($order->items()->with('product:id,name')->get() as $item) {
            DailyOperationLog::write(
                storeId: $storeId,
                content: "POS销售: {$item->product?->name} {$item->qty}{$item->product?->unit} 单价{$item->unit_price}，单号{$order->order_no}",
                intent: 'sale_report',
                source: 2,
                isOperational: true,
                productId: $item->product_id,
                qtyChange: -(float) $item->qty,
                referenceType: 'sales_order',
                referenceId: $order->id,
                operatorId: $request->user()->id,
            );
        }

        return response()->json([
            'message' => '销售单创建成功',
            'order_no' => $order->order_no,
            'id' => $order->id,
        ], 201);
    }

    /**
     * 销售单详情（含明细）。
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $order = SalesOrder::with(['cashier:id,name', 'store:id,name', 'items.product:id,name,unit'])
            ->where('store_id', $storeId)
            ->findOrFail($id);

        return response()->json(['data' => $order]);
    }

    /**
     * 补录销售情况（统一入口，支持三种场景）。
     *
     * type=sold_out  — 卖完了：sold_qty = 当前库存全部，库存归零，记录售罄时间
     * type=remaining — 还剩多少：sold_qty = 当前库存 - remaining_qty，库存改为 remaining_qty
     * type=qty       — 卖了多少：sold_qty = 指定数量，库存扣减对应量
     *
     * POST /api/sales/supplement
     */
    public function supplement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_name' => 'required|string|max:100',
            'type' => 'required|in:sold_out,remaining,qty',
            'remaining_qty' => 'required_if:type,remaining|nullable|numeric|min:0',
            'sold_qty' => 'required_if:type,qty|nullable|numeric|min:0.001',
            'unit_price' => 'nullable|numeric|min:0',
            'occurred_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }
        $occurredAt = isset($data['occurred_at']) ? now()->parse($data['occurred_at']) : now();

        $result = DB::transaction(function () use ($data, $storeId, $occurredAt, $request) {
            $product = Product::findOrCreateByName($data['product_name']);

            $inventory = Inventory::firstOrCreate(
                ['store_id' => $storeId, 'product_id' => $product->id],
                ['current_qty' => 0, 'available_qty' => 0, 'locked_qty' => 0],
            );

            $qtyBefore = (float) $inventory->current_qty;

            // 根据 type 计算 soldQty 和 qtyAfter
            [$soldQty, $qtyAfter] = match ($data['type']) {
                'sold_out' => [$qtyBefore, 0.0],
                'remaining' => [max(0, $qtyBefore - (float) $data['remaining_qty']), (float) $data['remaining_qty']],
                'qty' => [(float) $data['sold_qty'], max(0, $qtyBefore - (float) $data['sold_qty'])],
            };

            if ($soldQty <= 0) {
                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyBefore,
                    'sold_qty' => 0,
                    'sold_amount' => 0,
                    'sold_out_at' => null,
                    'skipped' => true,
                    'skip_reason' => '无可售数量（当前库存已不足）',
                ];
            }

            $unitPrice = (float) ($data['unit_price'] ?? 0);
            $soldAmount = round($soldQty * $unitPrice, 2);
            $saleDate = $occurredAt->toDateString();

            // 库存流水
            InventoryTransaction::create([
                'store_id' => $storeId,
                'product_id' => $product->id,
                'transaction_type' => 2,
                'qty_change' => -$soldQty,
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyAfter,
                'unit_cost' => $unitPrice ?: null,
                'operator_id' => $request->user()->id,
                'notes' => $data['notes'] ?? '远程补录销售',
                'created_at' => $occurredAt,
            ]);

            // 更新实时库存
            $inventory->update([
                'current_qty' => $qtyAfter,
                'available_qty' => $qtyAfter,
                'last_out_at' => $occurredAt,
                'last_sold_at' => $occurredAt,
            ]);

            // 每日快照（自动处理 sold_out_at）
            InventoryDailySnapshot::record(
                storeId: $storeId,
                productId: $product->id,
                qtyBefore: $qtyBefore,
                qtyChange: -$soldQty,
                qtyAfter: $qtyAfter,
                transactionType: 2,
                date: $saleDate,
                occurredAt: $occurredAt,
            );

            // 补录销售单
            $orderNo = 'SUP-'.$occurredAt->format('Ymd').'-'.strtoupper(Str::random(6));
            $order = SalesOrder::create([
                'store_id' => $storeId,
                'order_no' => $orderNo,
                'cashier_id' => null,
                'total_amount' => $soldAmount,
                'discount_amount' => 0,
                'paid_amount' => $soldAmount,
                'payment_method' => 1,
                'status' => 1,
                'sold_at' => $occurredAt,
                'notes' => '[补录] '.($data['notes'] ?? $data['type']),
            ]);

            SalesOrderItem::create([
                'sales_order_id' => $order->id,
                'product_id' => $product->id,
                'qty' => $soldQty,
                'unit_price' => $unitPrice,
                'discount_amount' => 0,
                'subtotal' => $soldAmount,
            ]);

            // 更新每日销售汇总（来源：supplement）
            SalesDailySummary::accumulate(
                storeId: $storeId,
                productId: $product->id,
                date: $saleDate,
                qty: $soldQty,
                amount: $soldAmount,
                source: 'supplement',
            );

            $soldOutAt = InventoryDailySnapshot::where('store_id', $storeId)
                ->where('product_id', $product->id)
                ->where('date', $saleDate)
                ->value('sold_out_at');

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit' => $product->unit,
                'type' => $data['type'],
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyAfter,
                'sold_qty' => $soldQty,
                'sold_amount' => $soldAmount,
                'sales_order_no' => $order->order_no,
                'sold_out_at' => $soldOutAt ? \Carbon\Carbon::parse($soldOutAt)->format('H:i') : null,
                'skipped' => false,
                'skip_reason' => null,
            ];
        });

        if (! $result['skipped']) {
            $logContent = match ($result['type']) {
                'sold_out' => "售罄: {$result['product_name']} 全部 {$result['sold_qty']}{$result['unit']} 已售罄，清空时间 {$result['sold_out_at']}",
                'remaining' => "库存补录: {$result['product_name']} 售出 {$result['sold_qty']}{$result['unit']}，剩余 {$result['qty_after']}{$result['unit']}",
                'qty' => "销售补录: {$result['product_name']} 售出 {$result['sold_qty']}{$result['unit']}，剩余 {$result['qty_after']}{$result['unit']}",
            };

            DailyOperationLog::write(
                storeId: $storeId,
                content: $logContent,
                intent: $result['type'] === 'sold_out' ? 'sold_out' : 'supplement',
                source: 2,
                isOperational: true,
                productId: $result['product_id'],
                qtyChange: -$result['sold_qty'],
                referenceType: 'sales_order',
                operatorId: $request->user()->id,
                occurredAt: $occurredAt,
            );
        }

        return response()->json([
            'message' => $result['skipped'] ? $result['skip_reason'] : '补录成功',
            'data' => $result,
        ]);
    }

    /**
     * 按日期查询每日销售汇总（per-product 销量/金额/均价）。
     *
     * GET /api/sales/summary?date=YYYY-MM-DD
     */
    public function summary(Request $request): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $date = $request->input('date', today()->toDateString());

        $rows = SalesDailySummary::with('product:id,name,unit,is_fresh')
            ->where('store_id', $storeId)
            ->where('sale_date', $date)
            ->orderByDesc('sales_amount')
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'product_name' => $row->product?->name,
                'unit' => $row->product?->unit,
                'is_fresh' => $row->product?->is_fresh,
                'sales_qty' => (float) $row->sales_qty,
                'sales_amount' => (float) $row->sales_amount,
                'avg_selling_price' => $row->avg_selling_price ? (float) $row->avg_selling_price : null,
                'transaction_count' => $row->transaction_count,
                'sales_breakdown' => [
                    'pos' => ['qty' => (float) $row->pos_qty,        'amount' => (float) $row->pos_amount],
                    'supplement' => ['qty' => (float) $row->supplement_qty, 'amount' => (float) $row->supplement_amount],
                    'ai' => ['qty' => (float) $row->ai_qty,         'amount' => (float) $row->ai_amount],
                ],
            ]);

        return response()->json([
            'data' => [
                'date' => $date,
                'total_skus' => $rows->count(),
                'total_qty' => round($rows->sum('sales_qty'), 3),
                'total_amount' => round($rows->sum('sales_amount'), 2),
                'products' => $rows,
            ],
        ]);
    }

    /**
     * 每日销售报表（含汇总、来源明细、逐品种销售明细）。
     *
     * GET /api/sales/report?date=YYYY-MM-DD
     */
    public function dailyReport(Request $request): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $date = $request->input('date', today()->toDateString());

        // 当日已完成订单
        $orders = SalesOrder::where('store_id', $storeId)
            ->whereDate('sold_at', $date)
            ->where('status', 1)
            ->get();

        // per-product 汇总
        $summaries = SalesDailySummary::with('product:id,name,unit,is_fresh')
            ->where('store_id', $storeId)
            ->where('sale_date', $date)
            ->orderByDesc('sales_amount')
            ->get();

        $paymentLabels = [1 => '现金', 2 => '微信', 3 => '支付宝', 4 => '银行卡', 5 => '混合'];

        $paymentBreakdown = $orders->groupBy('payment_method')
            ->map(fn ($group, $method) => [
                'method' => $method,
                'label' => $paymentLabels[$method] ?? '其他',
                'count' => $group->count(),
                'amount' => round($group->sum('paid_amount'), 2),
            ])
            ->values();

        $products = $summaries->map(fn ($row) => [
            'product_id' => $row->product_id,
            'product_name' => $row->product?->name,
            'unit' => $row->product?->unit,
            'is_fresh' => $row->product?->is_fresh,
            'sales_qty' => (float) $row->sales_qty,
            'sales_amount' => (float) $row->sales_amount,
            'avg_price' => $row->avg_selling_price ? (float) $row->avg_selling_price : null,
            'transaction_count' => (int) $row->transaction_count,
            'sales_breakdown' => [
                'pos' => ['qty' => (float) $row->pos_qty,        'amount' => (float) $row->pos_amount],
                'supplement' => ['qty' => (float) $row->supplement_qty, 'amount' => (float) $row->supplement_amount],
                'ai' => ['qty' => (float) $row->ai_qty,         'amount' => (float) $row->ai_amount],
            ],
        ]);

        $totalQty = round($summaries->sum('sales_qty'), 3);
        $totalAmount = round($summaries->sum('sales_amount'), 2);

        return response()->json([
            'data' => [
                'date' => $date,
                'total_orders' => $orders->count(),
                'total_skus' => $summaries->count(),
                'total_qty' => $totalQty,
                'total_amount' => $totalAmount,
                'payment_breakdown' => $paymentBreakdown,
                'source_breakdown' => [
                    'pos' => [
                        'qty' => round($summaries->sum('pos_qty'), 3),
                        'amount' => round($summaries->sum('pos_amount'), 2),
                    ],
                    'supplement' => [
                        'qty' => round($summaries->sum('supplement_qty'), 3),
                        'amount' => round($summaries->sum('supplement_amount'), 2),
                    ],
                    'ai' => [
                        'qty' => round($summaries->sum('ai_qty'), 3),
                        'amount' => round($summaries->sum('ai_amount'), 2),
                    ],
                ],
                'products' => $products,
            ],
        ]);
    }

    /**
     * 今日销售汇总（总金额、总单数、各支付方式占比）。
     */
    public function todaySummary(Request $request): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $orders = SalesOrder::where('store_id', $storeId)
            ->whereDate('sold_at', today())
            ->where('status', 1)
            ->get();

        // 今日销售汇总（含来源明细）
        $summaries = SalesDailySummary::where('store_id', $storeId)
            ->where('sale_date', today())
            ->get();

        $paymentBreakdown = $orders->groupBy('payment_method')
            ->map(fn ($group) => round($group->sum('paid_amount'), 2));

        return response()->json([
            'data' => [
                'date' => today()->toDateString(),
                'total_orders' => $orders->count(),
                'total_amount' => round($orders->sum('paid_amount'), 2),
                'total_qty' => round($summaries->sum('sales_qty'), 3),
                'payment_breakdown' => $paymentBreakdown,
                'sales_breakdown' => [
                    'pos_qty' => round($summaries->sum('pos_qty'), 3),
                    'pos_amount' => round($summaries->sum('pos_amount'), 2),
                    'supplement_qty' => round($summaries->sum('supplement_qty'), 3),
                    'supplement_amount' => round($summaries->sum('supplement_amount'), 2),
                    'ai_qty' => round($summaries->sum('ai_qty'), 3),
                    'ai_amount' => round($summaries->sum('ai_amount'), 2),
                ],
            ],
        ]);
    }
}
