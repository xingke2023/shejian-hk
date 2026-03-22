<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiMessage;
use App\Models\AiSession;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AiAssistantController extends Controller
{
    public function __construct(private readonly AiService $aiService) {}

    /**
     * 接收文字或图片输入，AI解析后写入库存。
     */
    public function message(Request $request): JsonResponse
    {
        $request->validate([
            'text'         => 'nullable|string|max:2000',
            'image_base64' => 'nullable|string',
            'session_id'   => 'nullable|integer|exists:ai_sessions,id',
        ]);

        $startTime = microtime(true);

        $text        = $request->input('text', '');
        $imageBase64 = $request->input('image_base64');

        // 确定输入类型
        $inputType = 1; // 文字
        if ($imageBase64 && $text) {
            $inputType = 4; // 混合
        } elseif ($imageBase64) {
            $inputType = 3; // 图片
        }

        // 获取或创建会话
        $session = $this->getOrCreateSession($request, $inputType);

        // 调用 AI 解析
        $parsed = $this->aiService->parseInventoryIntent($text, $imageBase64);

        $processingMs = (int) ((microtime(true) - $startTime) * 1000);

        // 写入用户消息
        $userMessage = AiMessage::create([
            'session_id'   => $session->id,
            'role'         => 1,
            'input_type'   => $inputType,
            'raw_content'  => $text,
            'image_urls'   => $imageBase64 ? ['[base64 image]'] : null,
            'intent'       => $parsed['intent'] ?? 'other',
            'entities'     => $parsed['items'] ?? [],
            'created_at'   => now(),
        ]);

        // 写入 AI 回复消息
        AiMessage::create([
            'session_id'       => $session->id,
            'role'             => 2,
            'input_type'       => 1,
            'ai_response'      => $parsed['reply'] ?? '',
            'dispatched_module' => 'inventory',
            'processing_time_ms' => $processingMs,
            'created_at'       => now(),
        ]);

        // 分发到库存
        $operations = [];
        if (! empty($parsed['items']) && $parsed['intent'] !== 'other') {
            $operations = $this->dispatchToInventory(
                $parsed['items'],
                $session->store_id,
                $request->user()->id,
                $userMessage->id
            );
        }

        return response()->json([
            'reply'      => $parsed['reply'] ?? '已收到您的信息。',
            'intent'     => $parsed['intent'] ?? 'other',
            'operations' => $operations,
            'session_id' => $session->id,
        ]);
    }

    /**
     * 接收语音文件，转文字后解析入库存。
     */
    public function voice(Request $request): JsonResponse
    {
        $request->validate([
            'audio'      => 'required|file|mimes:mp3,wav,m4a,webm,ogg|max:25600',
            'session_id' => 'nullable|integer|exists:ai_sessions,id',
        ]);

        $startTime = microtime(true);

        $file     = $request->file('audio');
        $filePath = $file->store('voice_temp', 'local');
        $fullPath = Storage::disk('local')->path($filePath);

        // 语音转文字
        $transcribedText = $this->aiService->transcribeVoice($fullPath);

        Storage::disk('local')->delete($filePath);

        if (empty($transcribedText)) {
            return response()->json([
                'reply'  => '语音识别失败，请重新录制或改用文字输入。',
                'intent' => 'other',
            ], 422);
        }

        // 获取或创建会话
        $session = $this->getOrCreateSession($request, 2); // channel=1 APP语音

        // AI 解析
        $parsed = $this->aiService->parseInventoryIntent($transcribedText);

        $processingMs = (int) ((microtime(true) - $startTime) * 1000);

        // 写入用户消息
        $userMessage = AiMessage::create([
            'session_id'      => $session->id,
            'role'            => 1,
            'input_type'      => 2,
            'transcribed_text' => $transcribedText,
            'intent'          => $parsed['intent'] ?? 'other',
            'entities'        => $parsed['items'] ?? [],
            'created_at'      => now(),
        ]);

        // 写入 AI 回复
        AiMessage::create([
            'session_id'         => $session->id,
            'role'               => 2,
            'input_type'         => 1,
            'ai_response'        => $parsed['reply'] ?? '',
            'dispatched_module'  => 'inventory',
            'processing_time_ms' => $processingMs,
            'created_at'         => now(),
        ]);

        $operations = [];
        if (! empty($parsed['items']) && $parsed['intent'] !== 'other') {
            $operations = $this->dispatchToInventory(
                $parsed['items'],
                $session->store_id,
                $request->user()->id,
                $userMessage->id
            );
        }

        return response()->json([
            'transcribed_text' => $transcribedText,
            'reply'            => $parsed['reply'] ?? '已收到您的语音信息。',
            'intent'           => $parsed['intent'] ?? 'other',
            'operations'       => $operations,
            'session_id'       => $session->id,
        ]);
    }

    /**
     * 会话列表。
     */
    public function sessions(Request $request): JsonResponse
    {
        $sessions = AiSession::where('user_id', $request->user()->id)
            ->orderByDesc('started_at')
            ->paginate(20);

        return response()->json($sessions);
    }

    /**
     * 某会话的消息记录。
     */
    public function sessionMessages(Request $request, int $id): JsonResponse
    {
        $session = AiSession::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $messages = AiMessage::where('session_id', $session->id)
            ->orderBy('created_at')
            ->get();

        return response()->json($messages);
    }

    /**
     * 将 AI 解析的 items 写入库存。
     */
    private function dispatchToInventory(array $items, int $storeId, int $operatorId, int $messageId): array
    {
        $operations = [];

        foreach ($items as $item) {
            $productName = $item['product_name'] ?? '';
            $qty         = (float) ($item['qty'] ?? 0);
            $unit        = $item['unit'] ?? '斤';
            $action      = $item['action'] ?? 'in';

            if (empty($productName) || $qty <= 0) {
                continue;
            }

            DB::transaction(function () use ($productName, $qty, $unit, $action, $storeId, $operatorId, $messageId, &$operations): void {
                $product = Product::findOrCreateByName($productName);

                // 更新商品单位（如有新信息）
                if ($product->unit === '斤' && $unit !== '斤') {
                    $product->update(['unit' => $unit]);
                }

                $inventory = Inventory::firstOrCreate(
                    ['store_id' => $storeId, 'product_id' => $product->id],
                    ['current_qty' => 0, 'available_qty' => 0, 'locked_qty' => 0, 'avg_cost' => 0]
                );

                $qtyBefore = (float) $inventory->current_qty;

                // 根据动作计算变动量和事务类型
                [$qtyChange, $transactionType, $newQty] = match ($action) {
                    'in'     => [$qty, 1, $qtyBefore + $qty],
                    'out'    => [-$qty, 3, max(0, $qtyBefore - $qty)],
                    'adjust' => [$qty - $qtyBefore, 4, $qty],
                    default  => [$qty, 1, $qtyBefore + $qty],
                };

                // 更新库存
                $now = now();
                $updateData = [
                    'current_qty'   => $newQty,
                    'available_qty' => $newQty,
                    'updated_at'    => $now,
                ];

                if ($action === 'in') {
                    $updateData['last_in_at'] = $now;
                } elseif ($action === 'out') {
                    $updateData['last_out_at'] = $now;
                } elseif ($action === 'adjust') {
                    $updateData['last_counted_at'] = $now;
                }

                $inventory->update($updateData);

                // 写流水
                InventoryTransaction::create([
                    'store_id'         => $storeId,
                    'product_id'       => $product->id,
                    'transaction_type' => $transactionType,
                    'qty_change'       => $qtyChange,
                    'qty_before'       => $qtyBefore,
                    'qty_after'        => $newQty,
                    'reference_type'   => 'ai_message',
                    'reference_id'     => $messageId,
                    'operator_id'      => $operatorId,
                    'notes'            => "{$productName} {$qty}{$unit} AI录入",
                    'created_at'       => $now,
                ]);

                $operations[] = [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'action'       => $action,
                    'qty'          => $qty,
                    'unit'         => $unit,
                    'qty_before'   => $qtyBefore,
                    'qty_after'    => $newQty,
                ];
            });
        }

        return $operations;
    }

    /**
     * 获取当前用户的进行中会话，或创建新会话。
     */
    private function getOrCreateSession(Request $request, int $inputType): AiSession
    {
        if ($sessionId = $request->input('session_id')) {
            $session = AiSession::where('user_id', $request->user()->id)->find($sessionId);
            if ($session) {
                return $session;
            }
        }

        $channelMap = [1 => 2, 2 => 1, 3 => 3, 4 => 2];
        $channel    = $channelMap[$inputType] ?? 2;

        return AiSession::create([
            'store_id'   => 1, // MVP 阶段固定门店
            'user_id'    => $request->user()->id,
            'channel'    => $channel,
            'status'     => 1,
            'started_at' => now(),
        ]);
    }
}
