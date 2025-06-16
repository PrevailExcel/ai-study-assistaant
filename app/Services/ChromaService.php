<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChromaService
{
    private string $baseUrl;
    private string $collectionName;

    public function __construct()
    {
        $this->baseUrl = config('chroma.base_url', 'http://localhost:8000');
        $this->collectionName = config('chroma.collection_name', 'study_materials');
    }

    /**
     * Create or get collection
     */
    public function initializeCollection(): bool
    {
        try {
            $response = Http::post("{$this->baseUrl}/api/v1/collections", [
                'name' => $this->collectionName,
                'metadata' => [
                    'description' => 'Study materials collection'
                ]
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('ChromaDB collection initialization failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add documents to collection
     */
    public function addDocuments(array $documents, array $metadatas = [], array $ids = []): bool
    {
        try {
            // Generate embeddings using a service (we'll need this)
            $embeddings = $this->generateEmbeddings($documents);

            $payload = [
                'ids' => $ids ?: $this->generateIds(count($documents)),
                'documents' => $documents,
                'embeddings' => $embeddings,
                'metadatas' => $metadatas ?: array_fill(0, count($documents), [])
            ];

            $response = Http::post(
                "{$this->baseUrl}/api/v1/collections/{$this->collectionName}/add",
                $payload
            );

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('ChromaDB add documents failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Query documents
     */
    public function queryDocuments(string $query, int $limit = 5): array
    {
        try {
            $queryEmbedding = $this->generateEmbeddings([$query]);

            $response = Http::post(
                "{$this->baseUrl}/api/v1/collections/{$this->collectionName}/query",
                [
                    'query_embeddings' => $queryEmbedding,
                    'n_results' => $limit,
                    'include' => ['documents', 'metadatas', 'distances']
                ]
            );

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            Log::error('ChromaDB query failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate embeddings (you'll need an embedding service)
     */
    private function generateEmbeddings(array $texts): array
    {
        // Option 1: Use OpenAI embeddings
        // Option 2: Use Cohere embeddings
        // Option 3: Use local embedding service
        
        // For now, using OpenAI as example
        return $this->generateOpenAIEmbeddings($texts);
    }

    private function generateOpenAIEmbeddings(array $texts): array
    {
        $embeddings = [];
        
        foreach ($texts as $text) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('openai.api_key'),
                'Content-Type' => 'application/json'
            ])->post('https://api.openai.com/v1/embeddings', [
                'model' => 'text-embedding-3-small',
                'input' => $text
            ]);

            if ($response->successful()) {
                $embeddings[] = $response->json()['data'][0]['embedding'];
            }
        }

        return $embeddings;
    }

    private function generateIds(int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = 'doc_' . uniqid();
        }
        return $ids;
    }
}