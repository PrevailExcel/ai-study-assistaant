<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChromaService
{
    private string $baseUrl;
    private string $tenant;
    private string $database;
    private string $collectionName;
    private string $collectionID;
    private string $embeddingService;

    public function __construct()
    {
        $this->baseUrl = config('chroma.base_url', 'http://127.0.0.1:8000');
        $this->tenant = config('chroma.tenant', 'default');
        $this->database = config('chroma.database', 'default');
        $this->collectionName = config('chroma.collection_name', 'study_materials');
        $this->collectionID = config('chroma.collection_id', 'study_materials_id');
        $this->embeddingService = config('chroma.embedding_service', 'sentence_transformers');
    }

    private function url(string $path): string
    {
        return "{$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases/{$this->database}/{$path}";
    }

    public function createDatabase(): bool
    {
        try {

            // Check if the collection already exists
            $checkResponse = Http::get("$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases/$this->database");
    
            if ($checkResponse->successful()) {
                logger()->info('ChromaDB dataabase already exists', [
                    'database' => $this->database,
                ]);
                return true;
            }

            $response = Http::post("{$this->baseUrl}/api/v2/tenants/{$this->tenant}/databases", [
                'name' => $this->database,
            ]);

            logger()->info('Initializing ChromaDB database', [
                'database' => $this->database,
                'response_status' => $response->status()
            ]);

            if ($response->failed()) {
                Log::error('Failed to initialize ChromaDB database', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('ChromaDB database creation failed: ' . $e->getMessage());
            return false;
        }
    }
    public function initializeCollection(): bool
    {
        try {
            // Check if the collection already exists
            $checkResponse = Http::get($this->url("collections/{$this->collectionName}"));
    
            if ($checkResponse->successful()) {
                logger()->info('ChromaDB collection already exists', [
                    'collection' => $this->collectionName,
                    'data' => $checkResponse->json(),
                ]);
                $this->collectionID = $checkResponse->json()['id'];
                return true;
            }
    
            // Try creating the collection
            $response = Http::post($this->url('collections'), [
                'name' => $this->collectionName,
                'metadata' => ['description' => 'Study materials collection']
            ]);
    
            logger()->info('Initializing ChromaDB collection', [
                'collection' => $this->collectionName,
                'response_status' => $response->status()
            ]);
    
            if ($response->failed()) {
                Log::error('Failed to initialize ChromaDB collection', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }
    
            logger()->info('ChromaDB collection initialized successfully', [
                'collection' => $this->collectionName
            ]);
    
            return true;
        } catch (\Exception $e) {
            Log::error('ChromaDB collection initialization failed: ' . $e->getMessage());
            return false;
        }
    }
    

    public function addDocuments(array $documents, array $metadatas = [], array $ids = []): bool
    {
        logger()->info('Adding documents to ChromaDB', [
            'documents_count' => count($documents),
            'collection' => $this->collectionName,
            'metadatas_count' => count($metadatas),
            'ids_count' => count($ids)
        ]);

        try {
            $embeddings = $this->generateEmbeddings($documents);

            if (empty($embeddings)) {
                Log::error('Failed to generate embeddings');
                return false;
            }

            $payload = [
                'ids' => $ids ?: $this->generateIds(count($documents)),
                'documents' => $documents,
                'embeddings' => $embeddings,
                'metadatas' => $metadatas ?: array_fill(0, count($documents), [])
            ];

            $response = Http::post($this->url("collections/{$this->collectionID}/add"), $payload);

            if ($response->failed()) {
                Log::error('Failed to add documents to ChromaDB', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('ChromaDB add documents failed: ' . $e->getMessage());
            return false;
        }
    }

    public function queryDocuments(string $query, int $limit = 5): array
    {
        try {
            $queryEmbedding = $this->generateEmbeddings([$query]);

            if (empty($queryEmbedding)) {
                Log::error('Failed to generate query embedding');
                return [];
            }

            $response = Http::post($this->url("collections/{$this->collectionID}/query"), [
                'query_embeddings' => $queryEmbedding,
                'n_results' => $limit,
                'include' => ['documents', 'metadatas', 'distances']
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('ChromaDB query failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('ChromaDB query failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate embeddings using free services
     */
    private function generateEmbeddings(array $texts): array
    {
        return match ($this->embeddingService) {
            'huggingface_free' => $this->generateHuggingFaceEmbeddings($texts),
            'sentence_transformers' => $this->generateSentenceTransformerEmbeddings($texts),
            'ollama' => $this->generateOllamaEmbeddings($texts),
            default => $this->generateSentenceTransformerEmbeddings($texts),
        };
    }


    /**
     * Option 1: Use Hugging Face Inference API (Free tier available)
     */
    private function generateHuggingFaceEmbeddings(array $texts): array
    {
        $embeddings = [];
        $apiToken = config('huggingface.api_token');

        if (empty($apiToken)) {
            Log::warning('Hugging Face API token not configured');
            return [];
        }

        foreach ($texts as $text) {
            $maxRetries = 3;
            $retryCount = 0;

            while ($retryCount < $maxRetries) {
                try {
                    $response = Http::timeout(30)
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $apiToken,
                            'Content-Type' => 'application/json'
                        ])
                        ->post('https://api-inference.huggingface.co/pipeline/feature-extraction/sentence-transformers/all-MiniLM-L6-v2', [
                            'inputs' => trim($text),
                            'options' => ['wait_for_model' => true]
                        ]);

                    if ($response->successful()) {
                        $result = $response->json();

                        // Handle different response formats
                        if (is_array($result) && isset($result[0]) && is_array($result[0])) {
                            $embeddings[] = $result[0]; // First sentence embedding
                        } elseif (is_array($result) && is_numeric($result[0])) {
                            $embeddings[] = $result; // Direct embedding array
                        } else {
                            Log::error('Unexpected Hugging Face response format', ['response' => $result]);
                            return [];
                        }
                        break; // Success, exit retry loop
                    } else {
                        $responseBody = $response->body();

                        // Handle model loading
                        if (str_contains($responseBody, 'loading')) {
                            Log::info('Model is loading, waiting...', ['attempt' => $retryCount + 1]);
                            sleep(10); // Wait 10 seconds for model to load
                            $retryCount++;
                            continue;
                        }

                        // Handle rate limiting
                        if ($response->status() === 429) {
                            Log::warning('Rate limited, waiting...', ['attempt' => $retryCount + 1]);
                            sleep(2); // Wait 2 seconds for rate limit
                            $retryCount++;
                            continue;
                        }

                        Log::error('Hugging Face embedding failed', [
                            'status' => $response->status(),
                            'response' => $responseBody,
                            'attempt' => $retryCount + 1
                        ]);
                        return [];
                    }
                } catch (\Exception $e) {
                    Log::error('Hugging Face embedding error: ' . $e->getMessage(), [
                        'attempt' => $retryCount + 1
                    ]);

                    if ($retryCount >= $maxRetries - 1) {
                        return [];
                    }

                    $retryCount++;
                    sleep(1); // Brief pause before retry
                }
            }

            // Add small delay between requests to avoid rate limiting
            if (count($texts) > 1) {
                usleep(500000); // 0.5 second delay
            }
        }

        return $embeddings;
    }

    /**
     * Option 2: Use local Sentence Transformers service
     * You need to run a local Python service for this
     */
    private function generateSentenceTransformerEmbeddings(array $texts): array
    {
        $localServiceUrl = config('chroma.local_embedding_url', 'http://localhost:8001');

        // First check if service is healthy
        try {
            $healthResponse = Http::timeout(5)->get($localServiceUrl . '/health');
            if (!$healthResponse->successful()) {
                Log::error('Local embedding service is not healthy', [
                    'url' => $localServiceUrl,
                    'status' => $healthResponse->status()
                ]);
                return [];
            }
        } catch (\Exception $e) {
            Log::error('Cannot connect to local embedding service: ' . $e->getMessage(), [
                'url' => $localServiceUrl
            ]);
            return [];
        }

        try {
            Log::info('Generating embeddings locally', [
                'texts_count' => count($texts),
                'service_url' => $localServiceUrl
            ]);

            $response = Http::timeout(60) // Longer timeout for batch processing
                ->retry(3, 1000) // Retry 3 times with 1 second delay
                ->post($localServiceUrl . '/embed', [
                    'texts' => $texts
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['embeddings']) && is_array($data['embeddings'])) {
                    Log::info('Local embeddings generated successfully', [
                        'count' => $data['count'] ?? count($data['embeddings']),
                        'dimension' => $data['dimension'] ?? 'unknown'
                    ]);

                    return $data['embeddings'];
                } else {
                    Log::error('Invalid response format from local embedding service', [
                        'response' => $data
                    ]);
                    return [];
                }
            } else {
                Log::error('Local embedding service request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return [];
            }
        } catch (\Exception $e) {
            Log::error('Local embedding service error: ' . $e->getMessage(), [
                'url' => $localServiceUrl,
                'texts_count' => count($texts)
            ]);
            return [];
        }
    }

    /**
     * Option 3: Use Ollama embeddings (completely free and local)
     */
    private function generateOllamaEmbeddings(array $texts): array
    {
        $embeddings = [];
        $ollamaUrl = config('chroma.ollama_url', 'http://localhost:11434');

        foreach ($texts as $text) {
            try {
                $response = Http::timeout(30)->post($ollamaUrl . '/api/embeddings', [
                    'model' => 'all-minilm', // or 'nomic-embed-text'
                    'prompt' => $text
                ]);

                if ($response->successful()) {
                    $embeddings[] = $response->json()['embedding'];
                } else {
                    Log::error('Ollama embedding failed', ['response' => $response->body()]);
                    return [];
                }
            } catch (\Exception $e) {
                Log::error('Ollama embedding error: ' . $e->getMessage());
                return [];
            }
        }

        return $embeddings;
    }

    /**
     * Generate unique IDs for documents
     */
    private function generateIds(int $count): array
    {
        return array_map(fn() => 'doc_' . uniqid(), range(1, $count));
    }
}
