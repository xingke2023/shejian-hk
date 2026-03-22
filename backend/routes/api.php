<?php

use App\Http\Controllers\Api\AiAssistantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\WeworkCallbackController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // AI 助手
    Route::post('/ai/message', [AiAssistantController::class, 'message']);
    Route::post('/ai/voice', [AiAssistantController::class, 'voice']);
    Route::get('/ai/sessions', [AiAssistantController::class, 'sessions']);
    Route::get('/ai/sessions/{id}/messages', [AiAssistantController::class, 'sessionMessages']);

    // 库存
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::get('/inventory/transactions', [InventoryController::class, 'transactions']);

    // 人才简历库
    Route::post('/resumes/parse', [ResumeController::class, 'parse']);
    Route::post('/resumes/batch', [ResumeController::class, 'batch']);
    Route::get('/resumes/search', [ResumeController::class, 'search']);
    Route::apiResource('/resumes', ResumeController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
});

// 企业微信回调
Route::get('/wework/callback', [WeworkCallbackController::class, 'verify']);
Route::post('/wework/callback', [WeworkCallbackController::class, 'receive']);

// Post routes (index and show are public, rest require auth)
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/posts', [PostController::class, 'store']);
    Route::put('/posts/{post}', [PostController::class, 'update']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);
});
