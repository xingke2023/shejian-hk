<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiMessage;
use App\Models\AiSession;
use App\Models\DailyOperationLog;
use App\Models\Inventory;
use App\Models\InventoryDailySnapshot;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesDailySummary;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\DamageRecord;
use App\Models\SupplierRefundClaim;
use App\Models\WeatherLog;
use App\Services\AiService;
use App\Services\SuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiAssistantController extends Controller
{
    public function __construct(
        private readonly AiService $aiService,
        private readonly SuggestionService $suggestionService,
    ) {}

    /**
     * 接收文字或图片输入，AI解析后写入库存。
     */
    public function message(Request $request): JsonResponse
    {
        $request->validate([
            'text' => 'nullable|string|max:2000',
            'image_base64' => 'nullable|string',
            'session_id' => 'nullable|integer|exists:ai_sessions,id',
        ]);

        $startTime = microtime(true);

        $text = $request->input('text') ?? '';
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
            'session_id' => $session->id,
            'role' => 1,
            'input_type' => $inputType,
            'raw_content' => $text,
            'image_urls' => $imageBase64 ? ['[base64 image]'] : null,
            'intent' => $parsed['intent'] ?? 'other',
            'entities' => $parsed['items'] ?? [],
            'created_at' => now(),
        ]);

        // 写入 AI 回复消息
        AiMessage::create([
            'session_id' => $session->id,
            'role' => 2,
            'input_type' => 1,
            'ai_response' => $parsed['reply'] ?? '',
            'dispatched_module' => 'inventory',
            'processing_time_ms' => $processingMs,
            'created_at' => now(),
        ]);

        // 分发到库存
        $operations = [];
        $intent = $parsed['intent'] ?? 'other';
        $queryIntents = ['inventory_query', 'sales_today_query', 'daily_overview_query', 'purchase_orders_query', 'daily_logs_query', 'weather_query', 'refund_claims_query', 'suggestions_query', 'product_query'];
        $isQuery = in_array($intent, $queryIntents);
        $isOp = ! in_array($intent, ['other', ...$queryIntents]);

        // 查询类：直接查 DB 返回数据，不写库存
        $cardType = null;
        $cardData = null;
        if ($isQuery) {
            $date = $parsed['date'] ?? now()->toDateString();
            [$cardType, $cardData] = $this->fetchQueryData($intent, $session->store_id, $date, $parsed['items'] ?? []);
        }

        if ($isOp && ! empty($parsed['items'])) {
            $operations = $this->dispatchToInventory(
                $parsed['items'],
                $session->store_id,
                $request->user()->id,
                $userMessage->id
            );
        }

        // 记录操作日志
        if ($isOp && ! empty($operations)) {
            foreach ($operations as $op) {
                DailyOperationLog::write(
                    storeId: $session->store_id,
                    content: 'AI助手: '.($text ?: '[图片输入]'),
                    intent: $intent,
                    source: 1,
                    isOperational: true,
                    productId: $op['product_id'],
                    qtyChange: $op['qty_after'] - $op['qty_before'],
                    operatorId: $request->user()->id,
                    referenceType: 'ai_message',
                    referenceId: $userMessage->id,
                );
            }
        } else {
            DailyOperationLog::write(
                storeId: $session->store_id,
                content: 'AI助手: '.($text ?: '[图片输入]'),
                intent: $intent,
                source: 1,
                isOperational: false,
                operatorId: $request->user()->id,
                referenceType: 'ai_message',
                referenceId: $userMessage->id,
            );
        }

        return response()->json([
            'reply' => $parsed['reply'] ?? '已收到您的信息。',
            'intent' => $intent,
            'operations' => $operations,
            'card_type' => $cardType,
            'card_data' => $cardData,
            'session_id' => $session->id,
        ]);
    }

    /**
     * 接收语音文件，转文字后解析入库存。
     */
    public function voice(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,m4a,webm,ogg|max:25600',
            'session_id' => 'nullable|integer|exists:ai_sessions,id',
        ]);

        $startTime = microtime(true);

        $file = $request->file('audio');
        $filePath = $file->store('voice_temp', 'local');
        $fullPath = Storage::disk('local')->path($filePath);

        // 语音转文字
        $transcribedText = $this->aiService->transcribeVoice($fullPath);

        Storage::disk('local')->delete($filePath);

        if (empty($transcribedText)) {
            return response()->json([
                'reply' => '语音识别失败，请重新录制或改用文字输入。',
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
            'session_id' => $session->id,
            'role' => 1,
            'input_type' => 2,
            'transcribed_text' => $transcribedText,
            'intent' => $parsed['intent'] ?? 'other',
            'entities' => $parsed['items'] ?? [],
            'created_at' => now(),
        ]);

        // 写入 AI 回复
        AiMessage::create([
            'session_id' => $session->id,
            'role' => 2,
            'input_type' => 1,
            'ai_response' => $parsed['reply'] ?? '',
            'dispatched_module' => 'inventory',
            'processing_time_ms' => $processingMs,
            'created_at' => now(),
        ]);

        $operations = [];
        $intent = $parsed['intent'] ?? 'other';
        $queryIntents = ['inventory_query', 'sales_today_query', 'daily_overview_query', 'purchase_orders_query', 'daily_logs_query', 'weather_query', 'refund_claims_query', 'suggestions_query', 'product_query'];
        $isQuery = in_array($intent, $queryIntents);
        $isOp = ! in_array($intent, ['other', ...$queryIntents]);

        $cardType = null;
        $cardData = null;
        if ($isQuery) {
            $date = $parsed['date'] ?? now()->toDateString();
            [$cardType, $cardData] = $this->fetchQueryData($intent, $session->store_id, $date, $parsed['items'] ?? []);
        }

        if ($isOp && ! empty($parsed['items'])) {
            $operations = $this->dispatchToInventory(
                $parsed['items'],
                $session->store_id,
                $request->user()->id,
                $userMessage->id
            );
        }

        // 记录今日操作日志
        if ($isOp && ! empty($operations)) {
            foreach ($operations as $op) {
                DailyOperationLog::write(
                    storeId: $session->store_id,
                    content: 'AI语音: '.$transcribedText,
                    intent: $intent,
                    source: 1,
                    isOperational: true,
                    productId: $op['product_id'],
                    qtyChange: $op['qty_after'] - $op['qty_before'],
                    operatorId: $request->user()->id,
                    referenceType: 'ai_message',
                    referenceId: $userMessage->id,
                );
            }
        } else {
            DailyOperationLog::write(
                storeId: $session->store_id,
                content: 'AI语音: '.$transcribedText,
                intent: $intent,
                source: 1,
                isOperational: false,
                operatorId: $request->user()->id,
                referenceType: 'ai_message',
                referenceId: $userMessage->id,
            );
        }

        return response()->json([
            'transcribed_text' => $transcribedText,
            'reply' => $parsed['reply'] ?? '已收到您的语音信息。',
            'intent' => $intent,
            'operations' => $operations,
            'card_type' => $cardType,
            'card_data' => $cardData,
            'session_id' => $session->id,
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
        $purchaseOrder = null; // 同一批进货共用一张进货单

        foreach ($items as $item) {
            $productName = $item['product_name'] ?? '';
            $qty = (float) ($item['qty'] ?? 0);
            $unit = $item['unit'] ?? '斤';
            $action = $item['action'] ?? 'in';

            if (empty($productName) || ($qty <= 0 && $action !== 'sold_out')) {
                continue;
            }

            // 有进货项时先建进货单（仅建一次）
            if ($action === 'in' && $purchaseOrder === null) {
                $purchaseOrder = PurchaseOrder::create([
                    'store_id' => $storeId,
                    'order_no' => PurchaseOrder::generateOrderNo($storeId),
                    'order_type' => 1,
                    'status' => 5,
                    'actual_delivery_date' => now()->toDateString(),
                    'expected_delivery_date' => now()->toDateString(),
                    'total_amount' => 0,
                    'notes' => 'AI录入',
                    'created_by' => $operatorId,
                ]);
            }

            DB::transaction(function () use ($productName, $qty, $unit, $action, $storeId, $operatorId, $messageId, $purchaseOrder, &$operations): void {
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
                // remaining: qty = 剩余量，售出量 = qtyBefore - qty
                [$qtyChange, $transactionType, $newQty] = match ($action) {
                    'in' => [$qty,                         1, $qtyBefore + $qty],
                    'sell' => [-$qty,                        2, max(0, $qtyBefore - $qty)],
                    'sold_out' => [-$qtyBefore,                  2, 0],
                    'remaining' => [-(max(0, $qtyBefore - $qty)), 2, $qty],
                    'out' => [-$qty,                        3, max(0, $qtyBefore - $qty)],
                    'adjust' => [$qty - $qtyBefore,            4, $qty],
                    default => [$qty,                         1, $qtyBefore + $qty],
                };

                // 更新库存
                $now = now();
                $updateData = [
                    'current_qty' => $newQty,
                    'available_qty' => $newQty,
                    'updated_at' => $now,
                ];

                if ($action === 'in') {
                    $updateData['last_in_at'] = $now;
                } elseif ($action === 'sell' || $action === 'sold_out') {
                    $updateData['last_out_at'] = $now;
                    $updateData['last_sold_at'] = $now;
                } elseif ($action === 'out') {
                    $updateData['last_out_at'] = $now;
                } elseif ($action === 'adjust') {
                    $updateData['last_counted_at'] = $now;
                }

                $inventory->update($updateData);

                // 写每日快照
                InventoryDailySnapshot::record(
                    storeId: $storeId,
                    productId: $product->id,
                    qtyBefore: $qtyBefore,
                    qtyChange: $qtyChange,
                    qtyAfter: $newQty,
                    transactionType: $transactionType,
                    date: $now->toDateString(),
                    occurredAt: $now,
                );

                // 写流水
                InventoryTransaction::create([
                    'store_id' => $storeId,
                    'product_id' => $product->id,
                    'transaction_type' => $transactionType,
                    'qty_change' => $qtyChange,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $newQty,
                    'reference_type' => $action === 'in' && $purchaseOrder ? 'purchase_order' : 'ai_message',
                    'reference_id' => $action === 'in' && $purchaseOrder ? $purchaseOrder->id : $messageId,
                    'operator_id' => $operatorId,
                    'notes' => $action === 'sold_out'
                        ? "{$productName} AI标记售罄"
                        : "{$productName} {$qty}{$unit} AI录入",
                    'created_at' => $now,
                ]);

                // 进货：写进货单明细
                if ($action === 'in' && $purchaseOrder) {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => $product->id,
                        'ordered_qty' => $qty,
                        'received_qty' => $qty,
                        'unit_price' => 0,
                        'total_price' => 0,
                    ]);
                }

                // 损耗：建损耗记录（自动关联最近一笔进货单/供应商）
                if ($action === 'out') {
                    $reason = $item['reason'] ?? '变质';
                    $poItem = \App\Models\PurchaseOrderItem::whereHas('purchaseOrder', fn ($q) => $q->where('store_id', $storeId)->where('status', 5))
                        ->where('product_id', $product->id)
                        ->orderByDesc('id')
                        ->first();

                    DamageRecord::create([
                        'store_id' => $storeId,
                        'product_id' => $product->id,
                        'purchase_order_item_id' => $poItem?->id,
                        'supplier_id' => $poItem?->supplier_id ?? $poItem?->purchaseOrder?->supplier_id,
                        'qty' => $qty,
                        'unit_cost' => $poItem?->unit_price ? (float) $poItem->unit_price : null,
                        'total_claimed' => $poItem?->unit_price ? round($qty * (float) $poItem->unit_price, 2) : null,
                        'reason' => $reason,
                        'status' => 1,
                        'occurred_at' => $now,
                        'operator_id' => $operatorId,
                        'notes' => 'AI录入',
                    ]);
                }

                // 销售出货 / 售罄：建补录销售单 + 更新每日汇总
                if ($action === 'sell' || $action === 'sold_out') {
                    $soldQtyForSummary = $action === 'sold_out' ? $qtyBefore : $qty;
                    $saleDate = $now->toDateString();

                    if ($soldQtyForSummary > 0) {
                        $order = SalesOrder::create([
                            'store_id' => $storeId,
                            'order_no' => 'AI-'.$now->format('Ymd').'-'.strtoupper(Str::random(6)),
                            'cashier_id' => null,
                            'total_amount' => 0,
                            'discount_amount' => 0,
                            'paid_amount' => 0,
                            'payment_method' => 1,
                            'status' => 1,
                            'sold_at' => $now,
                            'notes' => '[AI录入] '.$productName,
                        ]);

                        SalesOrderItem::create([
                            'sales_order_id' => $order->id,
                            'product_id' => $product->id,
                            'qty' => $soldQtyForSummary,
                            'unit_price' => 0,
                            'discount_amount' => 0,
                            'subtotal' => 0,
                        ]);
                    }

                    // 更新每日销售汇总（来源：ai）
                    SalesDailySummary::accumulate(
                        storeId: $storeId,
                        productId: $product->id,
                        date: $saleDate,
                        qty: $soldQtyForSummary,
                        amount: 0,
                        source: 'ai',
                    );
                }

                $operations[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'action' => $action,
                    'qty' => $qty,
                    'unit' => $unit,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $newQty,
                ];
            });
        }

        return $operations;
    }

    /**
     * 根据查询意图从 DB 取数据，返回 [card_type, card_data]。
     */
    private function fetchQueryData(string $intent, int $storeId, string $date, array $parsedItems = []): array
    {
        return match ($intent) {
            'inventory_query' => [
                'inventory',
                ['data' => Inventory::with('product')
                    ->where('store_id', $storeId)
                    ->get()
                    ->map(fn ($inv) => [
                        'current_qty' => $inv->current_qty,
                        'last_sold_at' => $inv->last_sold_at,
                        'product' => ['name' => $inv->product?->name, 'unit' => $inv->product?->unit],
                    ])->values()],
            ],

            'sales_today_query' => [
                'sales_today',
                (function () use ($storeId, $date): array {
                    $orders = SalesOrder::where('store_id', $storeId)
                        ->whereDate('sold_at', $date)
                        ->where('status', 1)
                        ->get();

                    return ['data' => [
                        'total_amount' => $orders->sum('total_amount'),
                        'total_orders' => $orders->count(),
                        'payment_breakdown' => $orders->groupBy('payment_method')
                            ->map(fn ($g) => $g->sum('paid_amount')),
                    ]];
                })(),
            ],

            'daily_overview_query' => [
                'daily_overview',
                ['data' => InventoryDailySnapshot::with('product')
                    ->where('store_id', $storeId)
                    ->where('date', $date)
                    ->get()
                    ->map(fn ($s) => [
                        'product_name' => $s->product?->name,
                        'opening_qty' => $s->opening_qty,
                        'received_qty' => $s->received_qty,
                        'sold_qty' => $s->sold_qty,
                        'closing_qty' => $s->closing_qty,
                        'sold_out_at' => $s->sold_out_at,
                    ])->values()],
            ],

            'purchase_orders_query' => [
                'purchase_orders',
                ['data' => PurchaseOrder::with('items.product')
                    ->where('store_id', $storeId)
                    ->whereDate('actual_delivery_date', $date)
                    ->latest()
                    ->get()],
            ],

            'daily_logs_query' => [
                'daily_logs',
                ['data' => DailyOperationLog::where('store_id', $storeId)
                    ->whereDate('created_at', $date)
                    ->latest()
                    ->get()
                    ->map(fn ($l) => [
                        'source' => $l->source,
                        'content' => $l->content,
                        'intent' => $l->intent,
                        'created_at' => $l->created_at,
                    ])->values()],
            ],

            'weather_query' => $this->fetchWeatherData($date, $storeId),

            'refund_claims_query' => [
                'refund_claims',
                ['data' => SupplierRefundClaim::with('supplier:id,name')
                    ->where('store_id', $storeId)
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get()
                    ->map(fn ($c) => [
                        'id' => $c->id,
                        'claim_no' => $c->claim_no,
                        'supplier_name' => $c->supplier?->name,
                        'status' => $c->status,
                        'total_items' => $c->total_items,
                        'total_qty' => $c->total_qty,
                        'total_amount' => $c->total_amount,
                        'created_at' => $c->created_at,
                    ])->values()],
            ],

            'suggestions_query' => [
                'suggestions',
                ['data' => $this->suggestionService->generate($storeId)],
            ],

            'product_query' => (function () use ($storeId, $date, $parsedItems): array {
                $productName = $parsedItems[0]['product_name'] ?? null;
                if (! $productName) {
                    return [null, null];
                }

                $product = Product::where('name', 'like', "%{$productName}%")->first();
                if (! $product) {
                    return ['product_detail', ['error' => "找不到商品：{$productName}"]];
                }

                $inventory = Inventory::where('store_id', $storeId)
                    ->where('product_id', $product->id)
                    ->first();

                $snapshot = InventoryDailySnapshot::where('store_id', $storeId)
                    ->where('product_id', $product->id)
                    ->where('date', $date)
                    ->first();

                $recentSales = SalesDailySummary::where('store_id', $storeId)
                    ->where('product_id', $product->id)
                    ->orderByDesc('date')
                    ->limit(7)
                    ->get()
                    ->map(fn ($s) => [
                        'date' => $s->date,
                        'sales_qty' => $s->sales_qty,
                        'sales_amount' => $s->sales_amount,
                        'avg_price' => $s->avg_price,
                    ])->values();

                $recentPurchases = PurchaseOrderItem::with('order:id,date,status,order_no')
                    ->where('product_id', $product->id)
                    ->whereHas('order', fn ($q) => $q->where('store_id', $storeId)->where('status', 3))
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get()
                    ->map(fn ($p) => [
                        'order_id' => $p->order?->id,
                        'order_no' => $p->order?->order_no,
                        'date' => $p->order?->date,
                        'qty' => $p->ordered_qty,
                        'unit_price' => $p->unit_price,
                    ])->values();

                $recentSalesOrders = SalesOrderItem::with('order:id,order_no,sold_at,payment_method,status')
                    ->where('product_id', $product->id)
                    ->whereHas('order', fn ($q) => $q->where('store_id', $storeId)->where('status', 1))
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get()
                    ->map(fn ($i) => [
                        'order_no' => $i->order?->order_no,
                        'sold_at' => $i->order?->sold_at,
                        'qty' => $i->qty,
                        'unit_price' => $i->unit_price,
                        'subtotal' => $i->subtotal,
                    ])->values();

                $recentDamage = DamageRecord::where('store_id', $storeId)
                    ->where('product_id', $product->id)
                    ->orderByDesc('occurred_at')
                    ->limit(5)
                    ->get()
                    ->map(fn ($d) => [
                        'date' => $d->occurred_at?->toDateString(),
                        'qty' => $d->qty,
                        'reason' => $d->reason,
                    ])->values();

                return ['product_detail', ['data' => [
                    'product' => ['id' => $product->id, 'name' => $product->name, 'unit' => $product->unit, 'is_fresh' => $product->is_fresh],
                    'current_qty' => $inventory?->current_qty ?? 0,
                    'last_sold_at' => $inventory?->last_sold_at,
                    'today' => $snapshot ? [
                        'opening_qty' => $snapshot->opening_qty,
                        'received_qty' => $snapshot->received_qty,
                        'sold_qty' => $snapshot->sold_qty,
                        'damage_qty' => $snapshot->damage_qty,
                        'closing_qty' => $snapshot->closing_qty,
                        'sold_out_at' => $snapshot->sold_out_at,
                    ] : null,
                    'recent_sales' => $recentSales,
                    'recent_sales_orders' => $recentSalesOrders,
                    'recent_purchases' => $recentPurchases,
                    'recent_damage' => $recentDamage,
                ]]];
            })(),

            default => [null, null],
        };
    }

    /**
     * 调用天气 API（带 DB 缓存），返回 [card_type, card_data]。
     */
    private function fetchWeatherData(string $date, int $storeId): array
    {
        $city = '香港';

        $existing = WeatherLog::where('date', $date)->where('city', $city)->first();
        if ($existing) {
            return ['weather', ['data' => [
                'city' => $city,
                'date' => $date,
                'condition' => $existing->weather,
                'temperature' => $existing->temperature_high,
                'temperature_high' => $existing->temperature_high,
                'temperature_low' => $existing->temperature_low,
                'humidity' => $existing->humidity,
                'rain_probability' => $existing->rain_probability,
                'suggestion' => $existing->description,
            ]]];
        }

        try {
            $response = Http::baseUrl(config('ai.base_url'))
                ->withToken(config('ai.api_key'))
                ->timeout(30)
                ->post('/chat/completions', [
                    'model' => config('ai.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => '你是天气查询助手。严格只返回JSON，不要任何其他文字。'],
                        ['role' => 'user', 'content' => "查询{$city}在{$date}的天气。返回格式：{\"weather\":\"天气状况\",\"temperature_high\":最高气温整数,\"temperature_low\":最低气温整数,\"humidity\":湿度整数,\"rain_probability\":降雨概率整数,\"uv_index\":紫外线指数整数,\"description\":\"一句话生鲜门店提示\"}"],
                    ],
                    'temperature' => 0.3,
                    'response_format' => ['type' => 'json_object'],
                ]);

            $content = $response->json('choices.0.message.content', '{}');
            $weather = json_decode($content, true) ?? [];

            if (! empty($weather)) {
                WeatherLog::firstOrCreate(
                    ['date' => $date, 'city' => $city],
                    [
                        'store_id' => $storeId,
                        'weather' => $weather['weather'] ?? '',
                        'temperature_high' => $weather['temperature_high'] ?? 0,
                        'temperature_low' => $weather['temperature_low'] ?? 0,
                        'humidity' => $weather['humidity'] ?? 0,
                        'rain_probability' => $weather['rain_probability'] ?? 0,
                        'uv_index' => $weather['uv_index'] ?? 0,
                        'description' => $weather['description'] ?? '',
                    ]
                );
            }

            return ['weather', ['data' => [
                'city' => $city,
                'date' => $date,
                'condition' => $weather['weather'] ?? '',
                'temperature' => $weather['temperature_high'] ?? null,
                'temperature_high' => $weather['temperature_high'] ?? null,
                'temperature_low' => $weather['temperature_low'] ?? null,
                'humidity' => $weather['humidity'] ?? null,
                'rain_probability' => $weather['rain_probability'] ?? null,
                'suggestion' => $weather['description'] ?? '',
            ]]];
        } catch (\Throwable $e) {
            Log::error('Weather fetch failed in AI controller', ['error' => $e->getMessage()]);

            return ['weather', ['data' => ['city' => $city, 'date' => $date]]];
        }
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
        $channel = $channelMap[$inputType] ?? 2;

        $storeId = $request->user()->resolveStoreId();

        return AiSession::create([
            'store_id' => $storeId,
            'user_id' => $request->user()->id,
            'channel' => $channel,
            'status' => 1,
            'started_at' => now(),
        ]);
    }
}
