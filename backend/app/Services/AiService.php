<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    private PendingRequest $client;

    private PendingRequest $visionClient;

    private PendingRequest $whisperClient;

    private string $model;

    private string $visionModel;

    private string $whisperModel;

    public function __construct()
    {
        // 文字 → DeepSeek
        $this->client = Http::baseUrl(config('ai.base_url'))
            ->withToken(config('ai.api_key'))
            ->timeout(60);

        // 图像 → 第三方
        $this->visionClient = Http::baseUrl(config('ai.vision_base_url'))
            ->withToken(config('ai.vision_api_key'))
            ->timeout(60);

        // 语音 → 第三方
        $this->whisperClient = Http::baseUrl(config('ai.whisper_base_url'))
            ->withToken(config('ai.whisper_api_key'))
            ->timeout(60);

        $this->model = config('ai.model');
        $this->visionModel = config('ai.vision_model');
        $this->whisperModel = config('ai.whisper_model');
    }

    /**
     * 解析用户输入的库存意图，返回结构化 JSON。
     *
     * @return array{intent: string, items: array, reply: string}
     */
    public function parseInventoryIntent(string $text, ?string $imageBase64 = null): array
    {
        $systemPrompt = <<<'PROMPT'
你是生鲜门店AI助手（舌尖香港）。识别用户意图，严格只返回以下JSON，不要其他文字：

{
  "intent": "purchase_receipt|sale_report|sold_out|remaining|stocktake|waste_report|inventory_query|sales_today_query|daily_overview_query|purchase_orders_query|daily_logs_query|weather_query|other",
  "date": "YYYY-MM-DD或null（查询类意图若用户指定了日期则填写）",
  "items": [{"product_name":"商品名","qty":数字,"unit":"单位","action":"in|sell|sold_out|remaining|out|adjust"}],
  "reply": "简短中文回复"
}

【写入类意图】
- purchase_receipt：进货到货，如"收到50斤胡萝卜"（action=in）
- sale_report：报售出量，如"卖了20斤苹果"（action=sell）
- sold_out：商品卖完，如"苹果卖完了"（action=sold_out，qty=0）
- remaining：报剩余量，如"番茄还剩5斤"（action=remaining）
- stocktake：盘点，如"白菜现有30斤"（action=adjust）
- waste_report：损耗/变质，如"豆腐坏了10斤"（action=out）

【查询类意图（items返回空数组，reply返回"正在为您查询…"）】
- inventory_query：查当前库存，如"查库存"、"现在有什么货"、"还剩多少"
- sales_today_query：查今日/历史销售，如"今天卖了多少"、"昨天营业额"、"哪天收入"，可带日期
- daily_overview_query：查每日概览，如"今天情况"、"今日总览"、"开盘情况"，可带日期
- purchase_orders_query：查进货单，如"今天进了什么"、"进货记录"，可带日期
- daily_logs_query：查操作日志，如"今天做了什么"、"操作记录"、"日志"
- weather_query：询问天气，如"今天天气"、"会下雨吗"、"明天天气"，可带日期，reply返回"正在为您查询天气…"

【其他】
- other：与以上均无关

单位规范：斤/个/箱/袋/瓶/千克/克，未说明默认"斤"。qty非负数。

【图片识别专项规则（有图片时优先适用）】
图片可能是：进货单/送货单/磅码单/收据/手写单据/货架照片/商品标签。
- 若图片是进货单/送货单/磅码单：intent=purchase_receipt，从图片中提取所有商品名和数量，action=in
- 若图片是货架/库存照片：intent=stocktake，识别可见商品及估算数量，action=adjust
- 若图片是销售小票/零售收据：intent=sale_report，提取商品和数量，action=sell
- 商品名优先用中文简称（如"西红柿"→"番茄"、"土豆"→"土豆"）
- 数量单位从图片读取，若无单位信息则默认"斤"
- 若图片模糊/无法识别，intent=other，reply说明无法识别并返回空items
- reply字段必须先描述识别结果（如"我识别到这是一张进货单，共X种商品：番茄50斤、胡萝卜30斤"），再询问用户（"已为您录入，如有出入请告知"）
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        if ($imageBase64) {
            // 图像输入 → 第三方视觉模型
            $messages[] = [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $text ?: '请识别图片。如果是进货单/送货单/磅码单，提取所有商品名称和数量录入进货；如果是货架或库存照片，识别商品和估算数量；如果是销售小票，提取售出商品和数量。'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,'.$imageBase64]],
                ],
            ];

            $response = $this->visionClient->post('/chat/completions', [
                'model' => $this->visionModel,
                'messages' => $messages,
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);
        } else {
            // 纯文字 → DeepSeek
            $messages[] = ['role' => 'user', 'content' => $text];

            $response = $this->client->post('/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'thinking' => ['type' => 'enabled'],
                'reasoning_effort' => 'high',
                'response_format' => ['type' => 'json_object'],
            ]);
        }

        if ($response->failed()) {
            Log::error('AI API error', ['status' => $response->status(), 'body' => $response->body()]);

            return [
                'intent' => 'other',
                'items' => [],
                'reply' => 'AI服务暂时不可用，请稍后重试。',
            ];
        }

        $content = $response->json('choices.0.message.content', '{}');
        $parsed = json_decode($content, true);

        if (! is_array($parsed)) {
            return [
                'intent' => 'other',
                'items' => [],
                'reply' => '无法解析您的输入，请描述商品名称和数量，例如：收到50斤胡萝卜。',
            ];
        }

        return $parsed;
    }

    /**
     * 语音文件转文字 — 第三方 Whisper 兼容接口。
     */
    public function transcribeVoice(string $filePath): string
    {
        $response = $this->whisperClient
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post('/audio/transcriptions', [
                'model' => $this->whisperModel,
                'language' => 'zh',
            ]);

        if ($response->failed()) {
            Log::error('Whisper API error', ['status' => $response->status(), 'body' => $response->body()]);

            return '';
        }

        return $response->json('text', '');
    }
}
