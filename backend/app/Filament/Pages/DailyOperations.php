<?php

namespace App\Filament\Pages;

use App\Models\DailyOperationLog;
use App\Models\Inventory;
use App\Models\InventoryDailySnapshot;
use App\Models\SalesDailySummary;
use App\Models\Store;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class DailyOperations extends Page
{
    protected static string $view = 'filament.pages.daily-operations';

    protected static ?string $navigationGroup = '销售管理';

    protected static ?string $navigationLabel = '每日营运情况';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?int $navigationSort = 0;

    public string $date = '';

    public string $storeId = '';

    #[Url]
    public string $activeFilter = 'sold';

    public bool $filterSoldOut = false;

    public bool $filterLowStock = false;

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    public function setFilter(string $filter): void
    {
        $this->activeFilter = $this->activeFilter === $filter ? '' : $filter;
        $this->filterSoldOut = false;
        $this->filterLowStock = false;
    }

    /** 门店选项（含"全部"） */
    public function getStoreOptions(): array
    {
        $options = ['' => '全部门店'];
        Store::orderBy('id')->get(['id', 'name'])->each(function ($s) use (&$options) {
            $options[(string) $s->id] = $s->name;
        });

        return $options;
    }

    public function getTitle(): string
    {
        return '每日营运情况';
    }

    public function getData(): array
    {
        $date = $this->date ?: today()->toDateString();
        $storeIds = $this->storeId !== '' ? [(int) $this->storeId] : Store::pluck('id')->toArray();

        // 当日快照（有交易的商品）
        $snapshots = InventoryDailySnapshot::with('product:id,name,unit,is_fresh')
            ->whereIn('store_id', $storeIds)
            ->where('date', $date)
            ->get()
            ->keyBy('product_id');

        // 昨日库存 = 该门店所有商品在查询日期之前最近一次的 closing_qty 集合
        $prevSnapshots = InventoryDailySnapshot::whereIn('store_id', $storeIds)
            ->where('date', '<', $date)
            ->orderBy('product_id')
            ->orderByDesc('date')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');

        // 当日销售汇总（含金额）
        $sales = SalesDailySummary::whereIn('store_id', $storeIds)
            ->where('sale_date', $date)
            ->get()
            ->keyBy('product_id');

        // 全部实时库存（含 last_sold_at）
        $inventoryMap = Inventory::with('product:id,name,unit,is_fresh')
            ->whereIn('store_id', $storeIds)
            ->get()
            ->keyBy('product_id');

        // 商品 ID 集合：门店所有商品（含无今日活动的），以实时库存为主，补充历史快照
        $productIds = collect($inventoryMap->keys())
            ->merge($prevSnapshots->keys())
            ->merge($snapshots->keys())
            ->merge($sales->keys())
            ->unique();

        // 所有商品
        $products = $productIds->map(function ($pid) use ($snapshots, $prevSnapshots, $sales, $inventoryMap) {
            $snap = $snapshots->get($pid);
            $prevSnap = $prevSnapshots->get($pid);
            $sale = $sales->get($pid);
            $inv = $inventoryMap->get($pid);
            $product = $snap?->product ?? $sale?->product ?? $inv?->product;

            $receivedQty = $snap ? (float) $snap->received_qty : 0;

            // 昨日库存 = 上一日快照的收档库存；无快照则用当日 opening_qty 兜底
            $prevClosingQty = $prevSnap
                ? (float) $prevSnap->closing_qty
                : ($snap ? (float) $snap->opening_qty : (float) ($inv?->current_qty ?? 0));

            // 开盘库存 = 昨日收档 + 今日进货
            $availableQty = $prevClosingQty + $receivedQty;

            $closingQty = $snap ? (float) $snap->closing_qty : (float) ($inv?->current_qty ?? 0);
            $soldQty = $sale ? (float) $sale->sales_qty : ($snap ? (float) $snap->sold_qty : 0);
            $soldAmount = $sale ? (float) $sale->sales_amount : 0;
            $isSoldOut = $closingQty <= 0;

            return [
                'product_id' => $pid,
                'product_name' => $product?->name ?? '未知',
                'unit' => $product?->unit ?? '',
                'is_fresh' => $product?->is_fresh ?? false,
                'opening_qty' => round($prevClosingQty, 3),   // 昨日收档库存（上一日快照）
                'received_qty' => $receivedQty,               // 今日进货合计
                'available_qty' => round($availableQty, 3),   // 开盘库存 = 昨日收档 + 今日进货
                'sold_qty' => $soldQty,
                'sold_amount' => $soldAmount,
                'transaction_count' => $sale?->transaction_count ?? 0,
                'damage_qty' => $snap ? (float) $snap->damage_qty : 0,
                'closing_qty' => $closingQty,
                'is_sold_out' => $isSoldOut,
                'sold_out_at' => $snap?->sold_out_at?->format('H:i'),
                'last_sold_at' => $inv?->last_sold_at?->format('H:i'),
            ];
        })->sortByDesc('sold_amount')->values();

        return [
            'date' => $date,
            'total_opening_qty' => round($products->sum('opening_qty'), 1),
            'total_received_skus' => $products->where('received_qty', '>', 0)->count(),
            'total_received_qty' => round($products->sum('received_qty'), 1),
            'total_available_qty' => round($products->sum('available_qty'), 1),
            'total_sold_skus' => $products->where('sold_qty', '>', 0)->count(),
            'total_sold_qty' => round($products->sum('sold_qty'), 1),
            'total_sold_amount' => round($products->sum('sold_amount'), 2),
            'total_sold_out' => $products->where('is_sold_out', true)->count(),
            'products' => $products,
        ];
    }

    public function getLogs(): array
    {
        $date = $this->date ?: today()->toDateString();
        $sourceLabels = [1 => 'AI', 2 => '手动', 3 => '后台'];
        $storeIds = $this->storeId !== '' ? [(int) $this->storeId] : Store::pluck('id')->toArray();

        return DailyOperationLog::with(['product:id,name', 'store:id,name'])
            ->whereIn('store_id', $storeIds)
            ->where('date', $date)
            ->orderByDesc('occurred_at')
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'time' => $log->occurred_at->format('H:i'),
                'source' => $sourceLabels[$log->source] ?? '?',
                'source_id' => $log->source,
                'store_name' => $log->store?->name,
                'content' => $log->content,
                'intent' => $log->intent,
                'is_operational' => $log->is_operational,
                'product_name' => $log->product?->name,
                'qty_change' => $log->qty_change ? (float) $log->qty_change : null,
            ])
            ->toArray();
    }
}
