<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * 商品列表（供远程人员查询 product_id）。
     *
     * GET /api/products?q=&category_id=&is_fresh=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category:id,name')
            ->where('status', 1)
            ->orderBy('name');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('is_fresh')) {
            $query->where('is_fresh', (bool) $request->input('is_fresh'));
        }

        $products = $query->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'code' => $p->code,
            'unit' => $p->unit,
            'spec' => $p->spec,
            'is_fresh' => $p->is_fresh,
            'category_id' => $p->category_id,
            'category_name' => $p->category?->name,
        ]);

        return response()->json(['data' => $products]);
    }
}
