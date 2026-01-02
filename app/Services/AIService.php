<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class AIService
{
    private string $provider;
    private string $embedding_provider;
    private ?string $apiKey;
    private ?string $baseUrl;
    private ?string $embedding_baseUrl;

    public function __construct()
    {
        $this->provider = config('ai.provider', 'gemini');
        $this->embedding_provider = config('ai.embedding_provider', 'ollama');
        $this->apiKey = config("ai.providers.{$this->provider}.api_key");
        $this->baseUrl = config("ai.providers.{$this->provider}.base_url");
        $this->embedding_baseUrl = config("ai.providers.{$this->embedding_provider}.base_url");
    }

    public function generateEmbedding(string $text): array
    {
        return match ($this->embedding_provider) {
            //log the provider being used for embedding
            'openai' => $this->openAIEmbedding($text),
            'ollama' => $this->ollamaEmbedding($text),
            'sentence-transformer' => $this->localSentenceTransformer($text),
            default => throw new \Exception("Embedding provider [{$this->provider}] not supported."),
        };
    }

    public function chat(array $messages, string $model = null, float $temperature = 0.7): string
    {
        logger()->info("Using chat provider: {$this->provider}");
        return match ($this->provider) {
            'openai' => $this->openAIChat($messages, $model ?? 'gpt-4-turbo'),
            'gemini' => $this->geminiChat($messages, $model ?? 'gemini-1.5-flash'),
            'deepseek' => $this->deepSeekChat($messages, $model ?? 'deepseek-chat'),
            'ollama' => $this->ollamaChat($messages, $model ?? 'llama3'),
            default => throw new \Exception("Chat provider [{$this->provider}] not supported."),
        };
    }

    /* ========== EMBEDDING METHODS ========== */

    private function openAIEmbedding(string $text): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->post("{$this->baseUrl}/embeddings", [
                    'model' => 'text-embedding-3-small',
                    'input' => $text,
                ]);

        return $response->json('data.0.embedding', []);
    }

    private function ollamaEmbedding(string $text): array
    {
        $response = Http::post("{$this->embedding_baseUrl}/api/embeddings", [
            'model' => 'nomic-embed-text',
            'prompt' => $text,
        ]);
        return $response->json('embedding', []);
    }
    public function ollamaBatch(array $texts): array
    {
        $results = [];

        foreach ($texts as $text) {
            $response = Http::post("{$this->embedding_baseUrl}/api/embeddings", [
                'model' => 'nomic-embed-text',
                'prompt' => $text,
            ]);

            $embedding = $response->json('embedding');

            if (empty($embedding)) {
                Log::error("Ollama returned empty embedding", [
                    'text' => mb_substr($text, 0, 200),
                    'response' => $response->json()
                ]);
                return []; // force failure in job
            }

            $results[] = $embedding;

            usleep(200_000); // 0.2s delay to avoid overload
        }

        return $results;
    }


    private function localSentenceTransformer(string $text): array
    {
        // Assuming you have a Python API running locally
        $response = Http::post("http://localhost:5000/embed", [
            'text' => $text,
        ]);

        return $response->json('embedding', []);
    }

    /* ========== CHAT METHODS ========== */

    private function openAIChat(array $messages, string $model): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->post("{$this->baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => $messages,
                ]);

        return $response->json('choices.0.message.content', '');
    }
    private function geminiChat(array $messages, string $model = 'gemini-1.5-flash'): string
    {
        $apiKey = $this->apiKey;
        $model = "gemini-2.5-flash";

        $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}";

        $contents = [];
        $systemPrompt = null;

        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $systemPrompt = $m['content'];
                continue;
            }

            $role = $m['role'] === 'assistant' ? 'model' : 'user';

            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $m['content']]
                ]
            ];
        }

        // 🔹 Prepend system prompt as first user message
        if ($systemPrompt) {
            array_unshift($contents, [
                'role' => 'user',
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ]);
        }

        if (empty($contents)) {
            logger()->warning('⚠️ No messages to send to Gemini.');
            return 'Sorry, no content to generate a response.';
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            ],
        ];

        $response = Http::post($url, $payload);

        $data = $response->json();

        logger()->info('🧠 Gemini raw response', [
            'model' => $model,
            'response' => $data,
        ]);

        return $data['candidates'][0]['content']['parts'][0]['text']
            ?? 'Sorry, I could not generate a response at this time.';
    }


    private function deepSeekChat(array $messages, string $model): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->post("{$this->baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => $messages,
                ]);

        return $response->json('choices.0.message.content', '');
    }

    private function ollamaChat(array $messages, string $model): string
    {
        $prompt = collect($messages)->map(fn($m) => "{$m['role']}: {$m['content']}")->implode("\n");

        $response = Http::post("{$this->baseUrl}/api/generate", [
            'model' => $model,
            'prompt' => $prompt,
        ]);

        return $response->json('response', '');
    }
}
