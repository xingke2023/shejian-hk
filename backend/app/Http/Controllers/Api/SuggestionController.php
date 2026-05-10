<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryDailySnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SuggestionController extends Controller
{
    /**
     * 生成进货建议和促销建议。
     *
     * GET /api/suggestions
     */
    public function suggestions(Request $request): JsonResponse
    {
        $storeId = $request->user()->resolveStoreId();
        if (! $storeId) {
            return response()->json(['message' => '该账号未关联任何门店'], 403);
        }

        $today = today()->toDateString();
        $sevenDaysAgo = today()->subDays(7)->toDateString();

        // 当前库存（含商品信息）
        $inventories = Inventory::with('product')
            ->where('store_id', $storeId)
            ->get()
            ->keyBy('product_id');

        // 近7天快照（按商品分组）
        $snapshots = InventoryDailySnapshot::with('product:id,name,unit,is_fresh,shelf_life_days')
            ->where('store_id', $storeId)
            ->whereBetween('date', [$sevenDaysAgo, $today])
            ->orderBy('date')
            ->get()
            ->groupBy('product_id');

        $purchaseSuggestions = [];
        $promoSuggestions = [];

        foreach ($snapshots as $productId => $rows) {
            $inv = $inventories->get($productId);
            if (! $inv) {
                continue;
            }

            $product = $inv->product;
            if (! $product) {
                continue;
            }

            $currentQty = (float) $inv->current_qty;
            $lastSoldAt = $inv->last_sold_at;

            $salesDays = $rows->filter(fn ($s) => (float) $s->sold_qty > 0);
            $totalSold = $rows->sum(fn ($s) => (float) $s->sold_qty);
            $activeDays = $salesDays->count();
            $daysCovered = max(1, $rows->count());

            // 日均销量（按有销售的天数算，贴近实际销售速度）
            $dailySalesRate = $activeDays > 0
                ? round($totalSold / $activeDays, 3)
                : 0;

            // 综合日均（含无销售天，反映整体周转）
            $overallDailyRate = round($totalSold / $daysCovered, 3);

            $purchaseSuggestion = $this->buildPurchaseSuggestion(
                productId: $productId,
                productName: $product->name,
                unit: $product->unit ?? '斤',
                isFresh: (bool) $product->is_fresh,
                currentQty: $currentQty,
                dailySalesRate: $dailySalesRate,
                totalSold: $totalSold,
                activeDays: $activeDays,
                lastSoldAt: $lastSoldAt,
                rows: $rows,
            );

            if ($purchaseSuggestion) {
                $purchaseSuggestions[] = $purchaseSuggestion;
            }

            $promoSuggestion = $this->buildPromoSuggestion(
                productId: $productId,
                productName: $product->name,
                unit: $product->unit ?? '斤',
                isFresh: (bool) $product->is_fresh,
                shelfLifeDays: $product->shelf_life_days,
                currentQty: $currentQty,
                dailySalesRate: $dailySalesRate,
                overallDailyRate: $overallDailyRate,
                totalSold: $totalSold,
                activeDays: $activeDays,
                lastSoldAt: $lastSoldAt,
            );

            if ($promoSuggestion) {
                $promoSuggestions[] = $promoSuggestion;
            }
        }

        // 有库存但近7天完全无快照 → 长期滞销，直接归入促销建议
        foreach ($inventories as $productId => $inv) {
            if ($snapshots->has($productId)) {
                continue;
            }

            $product = $inv->product;
            if (! $product || (float) $inv->current_qty <= 0) {
                continue;
            }

            $currentQty = (float) $inv->current_qty;
            $daysSinceLastSale = $inv->last_sold_at
                ? (int) now()->diffInDays($inv->last_sold_at)
                : null;

            $promoSuggestions[] = [
                'product_id' => $productId,
                'product_name' => $product->name,
                'unit' => $product->unit ?? '斤',
                'is_fresh' => (bool) $product->is_fresh,
                'urgency' => 'high',
                'reason' => '近7天零销售，库存积压 '.(int) $currentQty.' '.($product->unit ?? '斤'),
                'action' => '建议大幅降价或捆绑促销，尽快清货',
                'current_qty' => $currentQty,
                'daily_sales_rate' => 0,
                'days_of_stock' => null,
                'days_since_last_sale' => $daysSinceLastSale,
            ];
        }

        // 按紧急程度排序
        $urgencyOrder = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($purchaseSuggestions, fn ($a, $b) => ($urgencyOrder[$a['urgency']] ?? 9) <=> ($urgencyOrder[$b['urgency']] ?? 9));
        usort($promoSuggestions, fn ($a, $b) => ($urgencyOrder[$a['urgency']] ?? 9) <=> ($urgencyOrder[$b['urgency']] ?? 9));

        return response()->json([
            'data' => [
                'generated_at' => now()->toDateTimeString(),
                'purchase_suggestions' => $purchaseSuggestions,
                'promo_suggestions' => $promoSuggestions,
            ],
        ]);
    }

    /** @param Collection<int, InventoryDailySnapshot> $rows */
    private function buildPurchaseSuggestion(
        int $productId,
        string $productName,
        string $unit,
        bool $isFresh,
        float $currentQty,
        float $dailySalesRate,
        float $totalSold,
        int $activeDays,
        mixed $lastSoldAt,
        Collection $rows,
    ): ?array {
        // 无销售速率且有库存 → 不建议补货
        if ($dailySalesRate <= 0 && $currentQty > 0) {
            return null;
        }

        $daysOfStock = $dailySalesRate > 0 ? round($currentQty / $dailySalesRate, 1) : null;

        // 建议进货量：鲜货备3天，非鲜货备5天
        $bufferDays = $isFresh ? 3 : 5;
        $suggestedQty = $dailySalesRate > 0 ? (int) ceil($dailySalesRate * $bufferDays) : 0;

        if ($currentQty <= 0 && $dailySalesRate > 0) {
            return [
                'product_id' => $productId,
                'product_name' => $productName,
                'unit' => $unit,
                'is_fresh' => $isFresh,
                'urgency' => 'urgent',
                'reason' => '当前库存为零（已售罄），近期日均销量 '.$dailySalesRate.' '.$unit,
                'action' => '建议立即进货约 '.$suggestedQty.' '.$unit,
                'current_qty' => $currentQty,
                'daily_sales_rate' => $dailySalesRate,
                'days_of_stock' => 0,
                'suggested_qty' => $suggestedQty,
            ];
        }

        if ($daysOfStock !== null && $daysOfStock <= 1) {
            return [
                'product_id' => $productId,
                'product_name' => $productName,
                'unit' => $unit,
                'is_fresh' => $isFresh,
                'urgency' => 'high',
                'reason' => '库存仅剩 '.(int) $currentQty.' '.$unit.'，按当前销速不足1天',
                'action' => '建议今日进货约 '.$suggestedQty.' '.$unit,
                'current_qty' => $currentQty,
                'daily_sales_rate' => $dailySalesRate,
                'days_of_stock' => $daysOfStock,
                'suggested_qty' => $suggestedQty,
            ];
        }

        if ($daysOfStock !== null && $daysOfStock <= 2) {
            return [
                'product_id' => $productId,
                'product_name' => $productName,
                'unit' => $unit,
                'is_fresh' => $isFresh,
                'urgency' => 'medium',
                'reason' => '库存剩余 '.(int) $currentQty.' '.$unit.'，日均销 '.$dailySalesRate.' '.$unit.'，仅够 '.$daysOfStock.' 天',
                'action' => '建议明日进货约 '.$suggestedQty.' '.$unit,
                'current_qty' => $currentQty,
                'daily_sales_rate' => $dailySalesRate,
                'days_of_stock' => $daysOfStock,
                'suggested_qty' => $suggestedQty,
            ];
        }

        return null;
    }

    private function buildPromoSuggestion(
        int $productId,
        string $productName,
        string $unit,
        bool $isFresh,
        ?int $shelfLifeDays,
        float $currentQty,
        float $dailySalesRate,
        float $overallDailyRate,
        float $totalSold,
        int $activeDays,
        mixed $lastSoldAt,
    ): ?array {
        if ($currentQty <= 0) {
            return null;
        }

        $daysOfStock = $dailySalesRate > 0 ? round($currentQty / $dailySalesRate, 1) : null;
        $daysSinceLastSale = $lastSoldAt
            ? (int) now()->diffInDays($lastSoldAt)
            : null;

        // 鲜货积压超过保质期一半
        if ($isFresh && $shelfLifeDays && $daysOfStock && $daysOfStock > ($shelfLifeDays / 2)) {
            return [
                'product_id' => $productId,
                'product_name' => $productName,
                'unit' => $unit,
                'is_fresh' => $isFresh,
                'urgency' => 'high',
                'reason' => '鲜货库存 '.(int) $currentQty.' '.$unit.'，按销速还需 '.$daysOfStock.' 天才能清完，超过保质期一半',
                'action' => '建议今日9折促销，或捆绑搭配销售',
                'current_qty' => $currentQty,
                'daily_sales_rate' => $dailySalesRate,
                'days_of_stock' => $daysOfStock,
            ];
        }

        // 库存积压超过7天用量
        if ($daysOfStock !== null && $daysOfStock > 7) {
            return [
                'product_id' => $productId,
                'product_name' => $productName,
                'unit' => $unit,
                'is_fresh' => $isFresh,
                'urgency' => 'medium',
                'reason' => '库存 '.(int) $currentQty.' '.$unit.' 按日均销量约需 '.$daysOfStock.' 天消化',
                'action' => '建议打折促销或与热销品捆绑，加速周转',
                'current_qty' => $currentQty,
                'daily_sales_rate' => $dailySalesRate,
                'days_of_stock' => $daysOfStock,
            ];
        }

        // 3天以上无销售但有库存
        if ($daysSinceLastSale !== null && $daysSinceLastSale >= 3) {
            return [
                'product_id' => $productId,
                'product_name' => $productName,
                'unit' => $unit,
                'is_fresh' => $isFresh,
                'urgency' => $isFresh ? 'high' : 'medium',
                'reason' => '已 '.$daysSinceLastSale.' 天未售出，库存剩余 '.(int) $currentQty.' '.$unit,
                'action' => $isFresh ? '鲜货尽快降价处理，避免损耗' : '建议放置显眼位置或组合搭配销售',
                'current_qty' => $currentQty,
                'daily_sales_rate' => $dailySalesRate,
                'days_of_stock' => $daysOfStock,
            ];
        }

        // 近7天销售天数偏少但库存较多
        if ($activeDays <= 2 && $currentQty > 10 && $totalSold > 0) {
            return [
                'product_id' => $productId,
                'product_name' => $productName,
                'unit' => $unit,
                'is_fresh' => $isFresh,
                'urgency' => 'low',
                'reason' => '近7天仅 '.$activeDays.' 天有销售，库存仍有 '.(int) $currentQty.' '.$unit,
                'action' => '建议调整陈列位置或搭配推荐',
                'current_qty' => $currentQty,
                'daily_sales_rate' => $dailySalesRate,
                'days_of_stock' => $daysOfStock,
            ];
        }

        return null;
    }
}
