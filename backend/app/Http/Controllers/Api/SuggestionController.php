<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    public function __construct(private readonly SuggestionService $suggestionService) {}

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

        return response()->json(['data' => $this->suggestionService->generate($storeId)]);
    }
}
