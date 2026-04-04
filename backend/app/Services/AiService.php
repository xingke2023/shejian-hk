<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    private PendingRequest $client;

    private string $model;

    private string $visionModel;

    private string $whisperModel;

    public function __construct()
    {
        $this->client = Http::baseUrl(config('ai.base_url'))
            ->withToken(config('ai.api_key'))
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
你是生鲜门店AI助手（舌尖香港）。用户会描述门店进货、销售、库存或损耗情况，也可能是与库存无关的日常汇报。
你必须识别意图并提取商品信息，严格返回以下JSON格式，不要输出任何其他文字：

{
  "intent": "purchase_receipt|sale_report|sold_out|remaining|inventory_feedback|stocktake|waste_report|other",
  "items": [
    {"product_name": "商品名", "qty": 数字, "unit": "单位", "action": "in|sell|sold_out|remaining|out|adjust"}
  ],
  "reply": "简短中文确认回复，告知用户已录入的内容"
}

意图说明：
- purchase_receipt：进货到货，如"收到50斤胡萝卜"（action=in，qty=到货量）
- sale_report：有具体销售数量，如"卖了20斤苹果"（action=sell，qty=售出量）
- sold_out：商品完全售罄，如"苹果卖完了"（action=sold_out，qty=0）
- remaining：报告剩余库存，如"番茄还剩5斤"（action=remaining，qty=剩余量）
- stocktake：盘点上报精确库存，如"盘点白菜现有30斤"（action=adjust，qty=实际量）
- waste_report：损耗/报废/变质，不是销售，如"豆腐坏了10斤"（action=out，qty=损耗量）
- other：与库存无关的信息，如"今天来了很多顾客"

单位规范：斤/个/箱/袋/瓶/千克/克。如用户未说明单位，默认"斤"。
qty必须是非负数；sold_out 时 qty 填 0。
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        if ($imageBase64) {
            $messages[] = [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $text ?: '请识别图片中的商品和数量'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,'.$imageBase64]],
                ],
            ];
        } else {
            $messages[] = ['role' => 'user', 'content' => $text];
        }

        $model = $imageBase64 ? $this->visionModel : $this->model;

        $response = $this->client->post('/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
        ]);

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
     * 语音文件转文字（Whisper 兼容接口）。
     */
    public function transcribeVoice(string $filePath): string
    {
        $response = Http::baseUrl(config('ai.base_url'))
            ->withToken(config('ai.api_key'))
            ->timeout(60)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post('/audio/transcriptions', [
                'model' => $this->whisperModel,
                'language' => 'zh',
            ]);

        if ($response->failed()) {
            Log::error('Whisper API error', ['status' => $response->status()]);

            return '';
        }

        return $response->json('text', '');
    }
}
