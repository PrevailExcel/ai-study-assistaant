<?php

namespace App\Services;

use App\Services\AIService;
use Illuminate\Support\Collection;

class RerankingService
{
    protected AIService $ai;

    public function __construct(AIService $ai)
    {
        $this->ai = $ai;
    }

    /**
     * @param string $query
     * @param array $chunks
     * @param int $limit
     * @return array
     */
    public function rerank(string $query, array $chunks, int $limit = 8): array
    {
        if (empty($chunks)) {
            return [];
        }

        $prompt = <<<TEXT
You are a retrieval evaluator.
Rank the following text chunks by how relevant they are to the user query.

Query: "$query"

Chunks:
TEXT;

        foreach ($chunks as $i => $chunk) {
            $prompt .= "\n[{$i}] {$chunk}\n";
        }

        $prompt .= <<<TEXT

Return ONLY a JSON array of indexes sorted by most relevant first.
Example output:
[2, 0, 1]
TEXT;

        $response = $this->ai->chat([
            ['role' => 'system', 'content' => $prompt],
        ]);

        $indexes = json_decode($response, true);

        // fallback if LLM fails
        if (!is_array($indexes)) {
            return array_slice($chunks, 0, $limit);
        }

        return collect($indexes)
            ->filter(fn ($i) => isset($chunks[$i]))
            ->take($limit)
            ->map(fn ($i) => $chunks[$i])
            ->values()
            ->toArray();
    }
}
