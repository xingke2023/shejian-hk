<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * 当前门店库存列表。
     */
    public function index(Request $request): JsonResponse
    {
        $storeId = 1; // MVP 阶段固定门店

        $inventory = Inventory::with('product:id,name,unit,is_fresh,category_id')
            ->where('store_id', $storeId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($item) => [
                'id'           => $item->id,
                'product_id'   => $item->product_id,
                'product_name' => $item->product?->name,
                'unit'         => $item->product?->unit,
                'is_fresh'     => $item->product?->is_fresh,
                'current_qty'  => (float) $item->current_qty,
                'available_qty' => (float) $item->available_qty,
                'last_in_at'   => $item->last_in_at?->format('m-d H:i'),
                'last_out_at'  => $item->last_out_at?->format('m-d H:i'),
                'updated_at'   => $item->updated_at?->format('m-d H:i'),
            ]);

        return response()->json(['data' => $inventory]);
    }

    /**
     * 库存流水记录（最近100条）。
     */
    public function transactions(Request $request): JsonResponse
    {
        $storeId = 1;

        $typeLabels = [
            1 => '进货入库',
            2 => '销售出库',
            3 => '损耗',
            4 => '盘点调整',
            5 => '促销出库',
            6 => '调拨入',
            7 => '调拨出',
            8 => '退货入库',
        ];

        $transactions = InventoryTransaction::with('product:id,name,unit')
            ->where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($tx) => [
                'id'               => $tx->id,
                'product_name'     => $tx->product?->name,
                'unit'             => $tx->product?->unit,
                'transaction_type' => $tx->transaction_type,
                'type_label'       => $typeLabels[$tx->transaction_type] ?? '未知',
                'qty_change'       => (float) $tx->qty_change,
                'qty_before'       => (float) $tx->qty_before,
                'qty_after'        => (float) $tx->qty_after,
                'notes'            => $tx->notes,
                'created_at'       => $tx->created_at?->format('m-d H:i'),
            ]);

        return response()->json(['data' => $transactions]);
    }
}
