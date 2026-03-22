<?php

return [
    'base_url'      => env('AI_BASE_URL', 'https://api.openai.com/v1'),
    'api_key'       => env('AI_API_KEY', ''),
    'model'         => env('AI_MODEL', 'gpt-4o'),
    'vision_model'  => env('AI_VISION_MODEL', 'gpt-4o'),
    'whisper_model' => env('AI_WHISPER_MODEL', 'whisper-1'),
];
