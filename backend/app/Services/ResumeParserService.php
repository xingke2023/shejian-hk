<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResumeParserService
{
    private string $baseUrl;

    private string $apiKey;

    private string $model;

    private string $visionBaseUrl;

    private string $visionApiKey;

    private string $visionModel;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('ai.base_url'), '/');
        $this->apiKey = config('ai.api_key');
        $this->model = config('ai.model');
        $this->visionBaseUrl = rtrim(config('ai.vision_base_url'), '/');
        $this->visionApiKey = config('ai.vision_api_key');
        $this->visionModel = config('ai.vision_model');
    }

    /**
     * 从简历文本（+可选图片）解析为结构化数据。
     */
    public function parseResume(string $text, ?string $imageBase64 = null): array
    {
        $systemPrompt = <<<'PROMPT'
你是香港生鲜门店招聘助手。从用户提供的简历内容中提取结构化信息，返回严格JSON格式：
{
  "name": "姓名或null",
  "phone": "手机号或null",
  "gender": 0,
  "age": null,
  "districts": ["意向工作区域，香港地区名，如筲箕湾、柴湾、西湾河等"],
  "work_types": ["工作类型：全职/兼职/小时工"],
  "positions": ["意向岗位，如收银员、理货员、生鲜切配、清洁员等"],
  "experience_years": null,
  "salary_min": null,
  "salary_max": null,
  "salary_unit": 1,
  "education": null,
  "availability_date": null,
  "languages": ["语言能力，如粤语、普通话、英语"],
  "skills": ["技能标签"],
  "notes": "其他备注信息或null"
}
说明：gender(0未知/1男/2女)；salary_unit(1月/2日/3小时)；education(1初中/2高中/3大专/4本科)；availability_date格式YYYY-MM-DD。
仅返回JSON，不要其他文字。
PROMPT;

        $messages = [];

        if ($imageBase64) {
            $messages[] = [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => "请解析以下简历信息：\n{$text}"],
                    ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64,{$imageBase64}"]],
                ],
            ];
            $model = $this->visionModel;
        } else {
            $messages[] = ['role' => 'user', 'content' => "请解析以下简历信息：\n{$text}"];
            $model = $this->model;
        }

        try {
            [$url, $key] = $imageBase64
                ? ["{$this->visionBaseUrl}/chat/completions", $this->visionApiKey]
                : ["{$this->baseUrl}/chat/completions", $this->apiKey];

            $response = Http::withToken($key)
                ->timeout(30)
                ->post($url, [
                    'model' => $model,
                    'messages' => array_merge([['role' => 'system', 'content' => $systemPrompt]], $messages),
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                ]);

            $content = $response->json('choices.0.message.content', '{}');

            return json_decode($content, true) ?? [];
        } catch (\Throwable $e) {
            Log::error('ResumeParserService::parseResume error', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * 将自然语言搜索需求解析为结构化搜索条件。
     */
    public function parseSearchQuery(string $query): array
    {
        $systemPrompt = <<<'PROMPT'
你是香港生鲜门店招聘搜索助手。将用户的自然语言招聘需求转化为结构化搜索条件，返回严格JSON格式：
{
  "districts": ["相关地区名，需包含周边地区，如筲箕湾还需包含西湾河、柴湾"],
  "work_types": ["工作类型：全职/兼职/小时工"],
  "positions": ["相关岗位关键词"],
  "keywords": ["其他关键搜索词"]
}
注意：districts尽量多列举周边区域以提高召回率。若未提及则返回空数组。
仅返回JSON，不要其他文字。
PROMPT;

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(20)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $query],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                ]);

            $content = $response->json('choices.0.message.content', '{}');

            return json_decode($content, true) ?? [];
        } catch (\Throwable $e) {
            Log::error('ResumeParserService::parseSearchQuery error', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
