<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyOperationLog;
use App\Models\Inventory;
use App\Models\InventoryDailySnapshot;
use App\Models\InventoryTransaction;
use App\Models\SalesDailySummary;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    /**
     * 当前门店库存列表。
     * 同时生成静态 HTML 清单页面，响应中附带 page_url。
     */
    public function index(Request $request): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $store = \App\Models\Store::find($storeId);
        $storeName = $store?->name ?? "门店 #{$storeId}";

        $inventory = Inventory::with('product:id,name,unit,is_fresh,category_id')
            ->where('store_id', $storeId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'unit' => $item->product?->unit,
                'is_fresh' => $item->product?->is_fresh,
                'current_qty' => (float) $item->current_qty,
                'available_qty' => (float) $item->available_qty,
                'last_in_at' => $item->last_in_at?->format('m-d H:i'),
                'last_out_at' => $item->last_out_at?->format('m-d H:i'),
                'last_sold_at' => $item->last_sold_at?->format('m-d H:i'),
                'updated_at' => $item->updated_at ? \Carbon\Carbon::parse($item->updated_at)->format('m-d H:i') : null,
            ]);

        $pageUrl = $this->generateInventoryPage($storeId, $storeName, $inventory->toArray());

        return response()->json(['data' => $inventory, 'page_url' => $pageUrl]);
    }

    /**
     * 生成库存静态 HTML 清单页面，返回公开访问 URL。
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function generateInventoryPage(int $storeId, string $storeName, array $items): string
    {
        $generatedAt = now()->format('Y-m-d H:i:s');
        $total = count($items);
        $soldOutCount = collect($items)->where('current_qty', '<=', 0)->count();

        $rows = '';
        foreach ($items as $item) {
            $qty = $item['current_qty'];
            $statusClass = $qty <= 0 ? 'sold-out' : ($qty <= 5 ? 'low-stock' : '');
            $statusBadge = $qty <= 0
                ? '<span class="badge badge-danger">售罄</span>'
                : ($qty <= 5 ? '<span class="badge badge-warning">低库存</span>' : '');

            $rows .= sprintf(
                '<tr class="%s"><td>%s</td><td>%s %s</td><td class="qty">%s %s</td><td>%s</td><td>%s</td></tr>',
                $statusClass,
                htmlspecialchars((string) ($item['product_name'] ?? '')),
                htmlspecialchars((string) ($item['unit'] ?? '')),
                $statusBadge,
                $qty,
                htmlspecialchars((string) ($item['unit'] ?? '')),
                htmlspecialchars((string) ($item['last_sold_at'] ?? '—')),
                htmlspecialchars((string) ($item['updated_at'] ?? '—')),
            );
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-HK">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>舌尖香港 · 库存清单</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Helvetica Neue", sans-serif; background: #f5f5f5; color: #333; }
  .header { background: linear-gradient(135deg, #e8521a, #c0392b); color: #fff; padding: 20px 24px; }
  .header h1 { font-size: 20px; font-weight: 700; }
  .header p { font-size: 13px; opacity: .8; margin-top: 4px; }
  .stats { display: flex; gap: 12px; padding: 16px 24px; background: #fff; border-bottom: 1px solid #eee; flex-wrap: wrap; }
  .stat { background: #f9f9f9; border-radius: 8px; padding: 10px 16px; min-width: 100px; }
  .stat .num { font-size: 22px; font-weight: 700; color: #e8521a; }
  .stat .label { font-size: 12px; color: #888; margin-top: 2px; }
  .container { padding: 16px 24px; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
  th { background: #fafafa; padding: 11px 14px; text-align: left; font-size: 13px; color: #555; border-bottom: 2px solid #eee; }
  td { padding: 10px 14px; font-size: 14px; border-bottom: 1px solid #f2f2f2; vertical-align: middle; }
  td.qty { font-weight: 600; font-size: 15px; }
  tr.sold-out td { color: #bbb; }
  tr.sold-out td.qty { color: #e74c3c; }
  tr.low-stock td.qty { color: #e67e22; }
  tr:last-child td { border-bottom: none; }
  .badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 11px; margin-left: 6px; vertical-align: middle; }
  .badge-danger { background: #fdecea; color: #e74c3c; }
  .badge-warning { background: #fef9e7; color: #e67e22; }
  .footer { text-align: center; padding: 20px; font-size: 12px; color: #aaa; }
  @media (max-width: 600px) { th:nth-child(4), td:nth-child(4) { display: none; } .container { padding: 12px; } }
</style>
</head>
<body>
<div class="header">
  <h1>🥬 舌尖香港 · 库存清单</h1>
  <p>{$storeName}（门店 #{$storeId}）· 生成时间：{$generatedAt}</p>
</div>
<div class="stats">
  <div class="stat"><div class="num">{$total}</div><div class="label">商品总数</div></div>
  <div class="stat"><div class="num">{$soldOutCount}</div><div class="label">售罄商品</div></div>
  <div class="stat"><div class="num">{$generatedAt}</div><div class="label">更新时间</div></div>
</div>
<div class="container">
  <table>
    <thead><tr><th>商品名称</th><th>单位</th><th>当前库存</th><th>最后销售</th><th>更新时间</th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
<div class="footer">舌尖香港 · AI店长助手 · 页面每次查询库存时自动更新</div>
</body>
</html>
HTML;

        $dir = public_path('inventory');

        // 删除该门店旧的静态页
        foreach (glob("{$dir}/store-{$storeId}-*.html") ?: [] as $old) {
            @unlink($old);
        }

        $rand = random_int(100000, 999999);
        $filename = "store-{$storeId}-{$rand}.html";
        file_put_contents("{$dir}/{$filename}", $html);

        return config('app.url')."/inventory/{$filename}";
    }

    /**
     * 库存流水记录（最近100条）。
     */
    public function transactions(Request $request): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $typeLabels = [
            1 => '进货入库', 2 => '销售出库', 3 => '损耗',
            4 => '盘点调整', 5 => '促销出库', 6 => '调拨入',
            7 => '调拨出',   8 => '退货入库',
        ];

        $transactions = InventoryTransaction::with('product:id,name,unit')
            ->where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'product_name' => $tx->product?->name,
                'unit' => $tx->product?->unit,
                'transaction_type' => $tx->transaction_type,
                'type_label' => $typeLabels[$tx->transaction_type] ?? '未知',
                'qty_change' => (float) $tx->qty_change,
                'qty_before' => (float) $tx->qty_before,
                'qty_after' => (float) $tx->qty_after,
                'notes' => $tx->notes,
                'created_at' => $tx->created_at?->format('m-d H:i'),
            ]);

        return response()->json(['data' => $transactions]);
    }

    /**
     * 手动调整库存。
     *
     * 支持三种模式：
     *   sold_out  — 标记售罄（qty 设为 0，同步更新当日销售汇总）
     *   adjust    — 直接设定库存绝对值（盘点修正）
     *   damage    — 损耗报废（减少指定数量）
     *
     * POST /api/inventory/adjust
     */
    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'type' => 'required|string|in:sold_out,adjust,damage',
            'qty' => 'required_if:type,adjust|nullable|numeric|min:0',
            'qty_change' => 'required_if:type,damage|nullable|numeric|min:0.001',
            'sold_qty' => 'nullable|numeric|min:0',
            'sold_amount' => 'nullable|numeric|min:0',
            'occurred_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }
        $occurredAt = isset($data['occurred_at'])
            ? now()->parse($data['occurred_at'])
            : now();

        $result = DB::transaction(function () use ($data, $storeId, $occurredAt, $request) {
            $inventory = Inventory::with('product:id,name')
                ->firstOrCreate(
                    ['store_id' => $storeId, 'product_id' => $data['product_id']],
                    ['current_qty' => 0, 'available_qty' => 0, 'locked_qty' => 0],
                );

            $qtyBefore = (float) $inventory->current_qty;

            [$qtyAfter, $qtyChange, $transactionType] = match ($data['type']) {
                'sold_out' => [0,                              -$qtyBefore,              2],
                'adjust' => [(float) $data['qty'],           (float) $data['qty'] - $qtyBefore, 4],
                'damage' => [max(0, $qtyBefore - (float) $data['qty_change']), -(float) $data['qty_change'], 3],
            };

            InventoryTransaction::create([
                'store_id' => $storeId,
                'product_id' => $data['product_id'],
                'transaction_type' => $transactionType,
                'qty_change' => $qtyChange,
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyAfter,
                'operator_id' => $request->user()->id,
                'notes' => $data['notes'] ?? match ($data['type']) {
                    'sold_out' => '远程标记售罄',
                    'adjust' => '远程盘点修正',
                    'damage' => '远程登记损耗',
                },
                'created_at' => $occurredAt,
            ]);

            $inventoryUpdate = [
                'current_qty' => $qtyAfter,
                'available_qty' => $qtyAfter,
            ];

            if ($data['type'] === 'sold_out') {
                $inventoryUpdate['last_out_at'] = $occurredAt;
                $inventoryUpdate['last_sold_at'] = $occurredAt;
            } elseif ($data['type'] === 'damage') {
                $inventoryUpdate['last_out_at'] = $occurredAt;
            }

            $inventory->update($inventoryUpdate);

            InventoryDailySnapshot::record(
                storeId: $storeId,
                productId: $data['product_id'],
                qtyBefore: $qtyBefore,
                qtyChange: $qtyChange,
                qtyAfter: $qtyAfter,
                transactionType: $transactionType,
                date: $occurredAt->toDateString(),
                occurredAt: $occurredAt,
            );

            // 同步当日销售汇总（如果传了销售数量/金额）
            if (! empty($data['sold_qty']) && $data['sold_qty'] > 0) {
                $soldQty = (float) $data['sold_qty'];
                $soldAmt = (float) ($data['sold_amount'] ?? 0);

                $this->createSupplementSalesOrder(
                    storeId: $storeId,
                    productId: $data['product_id'],
                    soldQty: $soldQty,
                    soldAmt: $soldAmt,
                    note: $data['notes'] ?? '远程补录',
                    occurredAt: $occurredAt,
                );

                SalesDailySummary::accumulate(
                    storeId: $storeId,
                    productId: $data['product_id'],
                    date: $occurredAt->toDateString(),
                    qty: $soldQty,
                    amount: $soldAmt,
                    source: 'supplement',
                );
            }

            return [
                'product_id' => $data['product_id'],
                'product_name' => $inventory->product?->name,
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyAfter,
                'qty_change' => $qtyChange,
                'occurred_at' => $occurredAt->format('Y-m-d H:i'),
            ];
        });

        $intentMap = ['sold_out' => 'sold_out', 'adjust' => 'adjust', 'damage' => 'damage'];
        DailyOperationLog::write(
            storeId: $storeId,
            content: '手动调整: '.($data['notes'] ?? match ($data['type']) {
                'sold_out' => '标记售罄',
                'adjust' => '盘点修正',
                'damage' => '损耗报废',
            }),
            intent: $intentMap[$data['type']] ?? 'adjust',
            source: 2,
            isOperational: true,
            productId: $data['product_id'],
            qtyChange: $result['qty_change'],
            referenceType: 'inventory',
            referenceId: $data['product_id'],
            operatorId: $request->user()->id,
            occurredAt: $occurredAt,
        );

        return response()->json(['message' => '库存已更新', 'data' => $result]);
    }

    /**
     * 商品售罄补录：库存归零 + 自动以剩余库存量补录销售。
     *
     * 场景：远程人员说"今天的番茄卖完了"，系统自动：
     *   1. 售罄数量 = 当前剩余库存
     *   2. 库存清零，记录 sold_out_at
     *   3. 按 unit_price 补录销售流水（可选，无单价则金额记 0）
     *   4. 更新 sales_daily_summaries
     *   5. 写 daily_operation_logs
     *
     * POST /api/inventory/sold-out
     */
    public function soldOut(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_name' => 'required|string|max:100',
            'remaining_qty' => 'nullable|numeric|min:0',   // 传则表示"还剩多少"，不传表示"卖完了"
            'unit_price' => 'nullable|numeric|min:0',
            'occurred_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $occurredAt = isset($data['occurred_at'])
            ? now()->parse($data['occurred_at'])
            : now();

        $result = DB::transaction(function () use ($data, $storeId, $occurredAt, $request) {
            $product = \App\Models\Product::findOrCreateByName($data['product_name']);

            $inventory = Inventory::firstOrCreate(
                ['store_id' => $storeId, 'product_id' => $product->id],
                ['current_qty' => 0, 'available_qty' => 0, 'locked_qty' => 0],
            );

            $qtyBefore = (float) $inventory->current_qty;
            $remainingQty = isset($data['remaining_qty']) ? (float) $data['remaining_qty'] : 0;
            $qtyAfter = $remainingQty;  // 卖完了=0，还剩X=X

            // 已经不超过目标值，幂等处理
            if ($qtyBefore <= $qtyAfter) {
                $snapshot = InventoryDailySnapshot::where('store_id', $storeId)
                    ->where('product_id', $product->id)
                    ->where('date', $occurredAt->toDateString())
                    ->first();

                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'qty_before' => 0,
                    'sold_qty' => 0,
                    'sold_amount' => 0,
                    'sold_out_at' => $snapshot?->sold_out_at?->format('H:i'),
                    'already_zero' => true,
                ];
            }

            $soldQty = $qtyBefore - $qtyAfter;   // 实际售出量
            $unitPrice = (float) ($data['unit_price'] ?? 0);
            $soldAmount = round($soldQty * $unitPrice, 2);

            // 库存流水（type=2 销售出库）
            InventoryTransaction::create([
                'store_id' => $storeId,
                'product_id' => $product->id,
                'transaction_type' => 2,
                'qty_change' => -$soldQty,
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyAfter,
                'unit_cost' => $unitPrice ?: null,
                'operator_id' => $request->user()->id,
                'notes' => $data['notes'] ?? '远程补录售罄',
                'created_at' => $occurredAt,
            ]);

            // 库存更新为目标值
            $inventoryUpdate = [
                'current_qty' => $qtyAfter,
                'available_qty' => $qtyAfter,
                'last_out_at' => $occurredAt,
                'last_sold_at' => $occurredAt,
            ];
            $inventory->update($inventoryUpdate);

            // 快照：sold_qty 累加，closing_qty=目标值，qtyAfter=0 时自动写 sold_out_at
            InventoryDailySnapshot::record(
                storeId: $storeId,
                productId: $product->id,
                qtyBefore: $qtyBefore,
                qtyChange: -$soldQty,
                qtyAfter: $qtyAfter,
                transactionType: 2,
                date: $occurredAt->toDateString(),
                occurredAt: $occurredAt,
            );

            // 补录销售单
            $salesOrder = $this->createSupplementSalesOrder(
                storeId: $storeId,
                productId: $product->id,
                soldQty: $soldQty,
                soldAmt: $soldAmount,
                note: $data['notes'] ?? '售罄补录',
                occurredAt: $occurredAt,
            );

            // 更新当日销售汇总（来源：supplement）
            $saleDate = $occurredAt->toDateString();
            SalesDailySummary::accumulate(
                storeId: $storeId,
                productId: $product->id,
                date: $saleDate,
                qty: $soldQty,
                amount: $soldAmount,
                source: 'supplement',
            );

            // 读取快照里的 sold_out_at
            $soldOutAt = InventoryDailySnapshot::where('store_id', $storeId)
                ->where('product_id', $product->id)
                ->where('date', $saleDate)
                ->value('sold_out_at');

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit' => $product->unit,
                'qty_before' => $qtyBefore,
                'sold_qty' => $soldQty,
                'sold_amount' => $soldAmount,
                'sales_order_no' => $salesOrder->order_no,
                'sold_out_at' => $soldOutAt ? \Carbon\Carbon::parse($soldOutAt)->format('H:i') : null,
                'already_zero' => false,
            ];
        });

        if (! $result['already_zero']) {
            $remainingQty = isset($data['remaining_qty']) ? (float) $data['remaining_qty'] : 0;
            $logContent = $remainingQty > 0
                ? "库存补录: {$result['product_name']} 售出 {$result['sold_qty']}{$result['unit']}，剩余 {$remainingQty}{$result['unit']}"
                : "售罄补录: {$result['product_name']} 全部 {$result['sold_qty']}{$result['unit']} 已售罄，清空时间 {$result['sold_out_at']}";

            DailyOperationLog::write(
                storeId: $storeId,
                content: $logContent,
                intent: $remainingQty > 0 ? 'supplement' : 'sold_out',
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
            'message' => $result['already_zero'] ? '该商品库存已经是零' : '售罄已记录',
            'data' => $result,
        ]);
    }

    /**
     * 修正某天某商品的销售汇总（补录未走收银台的销售数据）。
     *
     * POST /api/inventory/sales-summary
     */
    public function updateSalesSummary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'date' => 'required|date_format:Y-m-d',
            'sales_qty' => 'required|numeric|min:0.001',
            'sales_amount' => 'required|numeric|min:0',
            'transaction_count' => 'nullable|integer|min:1',
            'occurred_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }
        $occurredAt = isset($data['occurred_at'])
            ? now()->parse($data['occurred_at'])
            : now()->parse($data['date'].' '.now()->format('H:i:s'));
        $soldQty = (float) $data['sales_qty'];
        $soldAmt = (float) $data['sales_amount'];

        $result = DB::transaction(function () use ($data, $storeId, $occurredAt, $soldQty, $soldAmt) {
            $salesOrder = $this->createSupplementSalesOrder(
                storeId: $storeId,
                productId: $data['product_id'],
                soldQty: $soldQty,
                soldAmt: $soldAmt,
                note: $data['notes'] ?? '远程补录销售数据',
                occurredAt: $occurredAt,
            );

            // 更新当日销售汇总（来源：supplement）
            SalesDailySummary::accumulate(
                storeId: $storeId,
                productId: $data['product_id'],
                date: $data['date'],
                qty: $soldQty,
                amount: $soldAmt,
                source: 'supplement',
            );

            // 同步快照 sold_qty（无实物出库，只更新计数）
            InventoryDailySnapshot::recordSupplement(
                storeId: $storeId,
                productId: $data['product_id'],
                soldQty: $soldQty,
                date: $data['date'],
            );

            return [
                'order_no' => $salesOrder->order_no,
                'sales_order_id' => $salesOrder->id,
                'product_id' => $data['product_id'],
                'date' => $data['date'],
                'sales_qty' => $newQty,
                'sales_amount' => $newAmount,
                'transaction_count' => $newCount,
            ];
        });

        DailyOperationLog::write(
            storeId: $storeId,
            content: '补录销售: '.($data['notes'] ?? '远程补录销售数据'),
            intent: 'supplement',
            source: 2,
            isOperational: true,
            productId: $data['product_id'],
            qtyChange: -(float) $data['sales_qty'],
            referenceType: 'sales_order',
            referenceId: $result['sales_order_id'] ?? null,
            operatorId: $request->user()->id,
            occurredAt: $occurredAt,
        );

        return response()->json(['message' => '销售数据已补录', 'data' => $result]);
    }

    /**
     * 每日库存运营概览。
     *
     * 联合展示：往日库存（开盘）、今日进货、今日可用、已售数量/金额、结算库存、售罄状态。
     *
     * GET /api/inventory/daily-overview?date=YYYY-MM-DD
     */
    public function dailyOverview(Request $request): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }
        $date = $request->input('date', today()->toDateString());

        // 今日快照（有交易的商品）
        $snapshots = InventoryDailySnapshot::with('product:id,name,unit,is_fresh')
            ->where('store_id', $storeId)
            ->where('date', $date)
            ->get()
            ->keyBy('product_id');

        // 昨日库存 = 该门店所有商品在查询日期之前最近一次的 closing_qty 集合
        // 用 MAX(date) per product 找到每个商品最近的快照，再 JOIN 取 closing_qty
        $prevSnapshots = InventoryDailySnapshot::where('store_id', $storeId)
            ->where('date', '<', $date)
            ->whereIn('product_id', function ($sub) use ($storeId, $date) {
                $sub->selectRaw('product_id')
                    ->from('inventory_daily_snapshots')
                    ->where('store_id', $storeId)
                    ->where('date', '<', $date);
            })
            ->orderBy('product_id')
            ->orderByDesc('date')
            ->get()
            ->unique('product_id') // 每个商品只取最近那条
            ->keyBy('product_id');

        // 今日销售汇总（金额数据）
        $sales = SalesDailySummary::where('store_id', $storeId)
            ->where('sale_date', $date)
            ->get()
            ->keyBy('product_id');

        // 实时库存（last_sold_at / current_qty）
        $inventoryMap = Inventory::with('product:id,name,unit,is_fresh')
            ->where('store_id', $storeId)
            ->get()
            ->keyBy('product_id');

        // 商品 ID 集合：门店所有商品（含无今日活动的），以实时库存为主，补充历史快照
        $productIds = collect($inventoryMap->keys())
            ->merge($prevSnapshots->keys())
            ->merge($snapshots->keys())
            ->merge($sales->keys())
            ->unique();

        // 组装完整商品列表
        $products = $productIds->map(function ($pid) use ($snapshots, $prevSnapshots, $sales, $inventoryMap) {
            $snap = $snapshots->get($pid);
            $prevSnap = $prevSnapshots->get($pid);
            $sale = $sales->get($pid);
            $inv = $inventoryMap->get($pid);
            $product = $snap?->product ?? $sale?->product ?? $inv?->product;

            $receivedQty = $snap ? (float) $snap->received_qty : 0;

            // 昨日库存 = 上一日快照收档；无快照则用当日 opening_qty 兜底
            $prevClosingQty = $prevSnap
                ? (float) $prevSnap->closing_qty
                : ($snap ? (float) $snap->opening_qty : (float) ($inv?->current_qty ?? 0));

            $closingQty = $snap ? (float) $snap->closing_qty : (float) ($inv?->current_qty ?? 0);
            $soldQty = $sale ? (float) $sale->sales_qty : ($snap ? (float) $snap->sold_qty : 0);
            $soldAmount = $sale ? (float) $sale->sales_amount : 0;
            $isSoldOut = $closingQty <= 0;

            // 来源明细（三路合计）
            $posQty = $sale ? (float) $sale->pos_qty : 0;
            $posAmount = $sale ? (float) $sale->pos_amount : 0;
            $supplementQty = $sale ? (float) $sale->supplement_qty : 0;
            $supplementAmount = $sale ? (float) $sale->supplement_amount : 0;
            $aiQty = $sale ? (float) $sale->ai_qty : 0;
            $aiAmount = $sale ? (float) $sale->ai_amount : 0;

            return [
                'product_id' => $pid,
                'product_name' => $product?->name ?? '未知',
                'unit' => $product?->unit ?? '',
                'is_fresh' => $product?->is_fresh ?? false,

                // 库存维度
                'opening_qty' => round($prevClosingQty, 3),               // 昨日收档（上一日快照）
                'received_qty' => $receivedQty,                           // 今日进货合计
                'available_qty' => round($prevClosingQty + $receivedQty, 3), // 开盘库存 = 昨日收档 + 今日进货
                'damage_qty' => $snap ? (float) $snap->damage_qty : 0,
                'adjustment_qty' => $snap ? (float) $snap->adjustment_qty : 0,
                'closing_qty' => $closingQty,
                'is_sold_out' => $isSoldOut,
                'sold_out_at' => $snap?->sold_out_at?->format('H:i'),
                'last_sold_at' => $inv?->last_sold_at?->format('H:i'),

                // 销售维度（总量）
                'sold_qty' => $soldQty,
                'sold_amount' => $soldAmount,
                'transaction_count' => $sale?->transaction_count ?? 0,

                // 销售来源明细
                'sales_breakdown' => [
                    'pos' => ['qty' => $posQty,        'amount' => $posAmount],
                    'supplement' => ['qty' => $supplementQty, 'amount' => $supplementAmount],
                    'ai' => ['qty' => $aiQty,         'amount' => $aiAmount],
                ],
            ];
        })->sortByDesc('sold_amount')->values();

        // 今日无活动但当前售罄的商品
        $soldOutNoActivity = $inventoryMap
            ->filter(fn ($inv) => $inv->current_qty <= 0 && ! $productIds->contains($inv->product_id))
            ->map(fn ($inv) => [
                'product_id' => $inv->product_id,
                'product_name' => $inv->product?->name ?? '未知',
                'unit' => $inv->product?->unit ?? '',
                'is_fresh' => $inv->product?->is_fresh ?? false,
                'opening_qty' => 0,
                'received_qty' => 0,
                'available_qty' => 0,
                'damage_qty' => 0,
                'adjustment_qty' => 0,
                'closing_qty' => 0,
                'is_sold_out' => true,
                'sold_out_at' => null,
                'last_sold_at' => $inv->last_sold_at?->format('H:i'),
                'sold_qty' => 0,
                'sold_amount' => 0,
                'transaction_count' => 0,
                'sales_breakdown' => [
                    'pos' => ['qty' => 0, 'amount' => 0],
                    'supplement' => ['qty' => 0, 'amount' => 0],
                    'ai' => ['qty' => 0, 'amount' => 0],
                ],
            ])->values();

        $all = $products->concat($soldOutNoActivity)->values();

        return response()->json([
            'data' => [
                'date' => $date,
                'total_received_skus' => $products->where('received_qty', '>', 0)->count(),
                'total_sold_skus' => $all->where('sold_qty', '>', 0)->count(),
                'total_sold_amount' => round($all->sum('sold_amount'), 2),
                'total_sold_out' => $all->where('is_sold_out', true)->count(),
                'products' => $all,
            ],
        ]);
    }

    /**
     * 今日操作日志——记录当天所有远程指令（AI / 手动 API / 后台确认收货）。
     *
     * GET /api/daily-logs?date=YYYY-MM-DD
     */
    public function dailyLogs(Request $request): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }
        $date = $request->input('date', today()->toDateString());

        $sourceLabels = [1 => 'AI助手', 2 => '手动API', 3 => 'Filament后台'];

        $logs = DailyOperationLog::with('product:id,name,unit')
            ->where('store_id', $storeId)
            ->where('date', $date)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'occurred_at' => $log->occurred_at->format('H:i'),
                'source' => $sourceLabels[$log->source] ?? '未知',
                'content' => $log->content,
                'intent' => $log->intent,
                'is_operational' => $log->is_operational,
                'product_name' => $log->product?->name,
                'qty_change' => $log->qty_change ? (float) $log->qty_change : null,
                'reference_type' => $log->reference_type,
                'reference_id' => $log->reference_id,
            ]);

        return response()->json([
            'data' => [
                'date' => $date,
                'total' => $logs->count(),
                'operational_count' => $logs->where('is_operational', true)->count(),
                'logs' => $logs,
            ],
        ]);
    }

    /**
     * 创建远程补录销售单（sales_order + sales_order_item）并返回 SalesOrder。
     * 不更新 sales_daily_summaries，调用方自行处理汇总行。
     */
    private function createSupplementSalesOrder(
        int $storeId,
        int $productId,
        float $soldQty,
        float $soldAmt,
        string $note,
        \Carbon\Carbon $occurredAt,
    ): SalesOrder {
        $unitPrice = $soldQty > 0 ? round($soldAmt / $soldQty, 4) : 0;

        $salesOrder = SalesOrder::create([
            'store_id' => $storeId,
            'order_no' => 'ADJ-'.$occurredAt->format('Ymd').'-'.strtoupper(Str::random(6)),
            'cashier_id' => null,
            'total_amount' => $soldAmt,
            'discount_amount' => 0,
            'paid_amount' => $soldAmt,
            'payment_method' => 1,
            'status' => 1,
            'sold_at' => $occurredAt,
            'notes' => '[远程补录] '.$note,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'product_id' => $productId,
            'qty' => $soldQty,
            'unit_price' => $unitPrice,
            'discount_amount' => 0,
            'subtotal' => $soldAmt,
            'cost_price' => null,
        ]);

        return $salesOrder;
    }
}
