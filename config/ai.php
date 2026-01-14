<?php

return [
    'provider' => env('AI_PROVIDER', 'gemini'),
    'embedding_provider' => env('EMBEDDING_PROVIDER', 'ollama'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => 'https://api.openai.com/v1',
        ],

        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        ],

        'sentence-transformer' => [
            'base_url' => env('SENTENCE_TRANSFORMER_URL', 'http://localhost:5000'),
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
        ],

        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY'),
            'base_url' => 'https://api.deepseek.com/v1',
        ],
    ],
];
