<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class QuadrantService
{
    private string $baseUrl;
    private ?string $apiKey;
    private string $collectionName;
    private int $vectorSize;
    private string $vectorName;

    public function __construct()
    {
        $this->baseUrl = config('services.quadrant.url');
        $this->apiKey = config('qdrant.api_key');
        $this->collectionName = config('services.quadrant.collection', 'chatbase_vectors');
        $this->vectorSize = config('qdrant.vector_size', 768); // Nombix embedding size
        $this->vectorName = 'nombix'; // Named vector for Qdrant >=1.2
    }

    /**
     * Initialize the collection if it doesn't exist
     */
    public function initializeCollection(): bool
    {
        try {
            $response = $this->makeRequest('GET', "/collections/{$this->collectionName}");

            if ($response->successful()) {
                Log::info("Qdrant collection already exists: {$this->collectionName}");
                return true;
            }

            Log::info("Creating Qdrant collection: {$this->collectionName}");

            $response = $this->makeRequest('PUT', "/collections/{$this->collectionName}", [
                'vectors' => [
                    $this->vectorName => [
                        'size' => $this->vectorSize,
                        'distance' => 'Cosine',
                    ],
                ],
                'optimizers_config' => [
                    'default_segment_number' => 2,
                ],
                'replication_factor' => 1,
            ]);

            if ($response->successful()) {
                Log::info("Qdrant collection created successfully");
                return true;
            }

            Log::error("Failed to create Qdrant collection", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;

        } catch (Exception $e) {
            Log::error("Error initializing Qdrant collection: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Store multiple embeddings in batch
     */
    public function storeBatch(array $points): bool
    {
        try {
            $formattedPoints = [];

            foreach ($points as $point) {
                $formattedPoints[] = [
                    'id' => $point['id'],
                    'vector' => [
                        $this->vectorName => $point['vector'],
                    ],
                    'payload' => $point['payload'] ?? [],
                ];
            }

            $response = $this->makeRequest('PUT', "/collections/{$this->collectionName}/points", [
                'points' => $formattedPoints,
            ]);

            if ($response->successful()) {
                Log::info("Batch stored in Qdrant", [
                    'count' => count($formattedPoints),
                ]);
                return true;
            }

            Log::error("Failed to store batch in Qdrant", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;

        } catch (Exception $e) {
            Log::error("Error storing batch in Qdrant: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Make HTTP request to Qdrant
     */
    private function makeRequest(string $method, string $endpoint, ?array $data = null)
    {
        $url = $this->baseUrl . $endpoint;

        $request = Http::timeout(30);

        if ($this->apiKey) {
            $request->withHeaders(['api-key' => $this->apiKey]);
        }

        return match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => throw new Exception("Unsupported HTTP method: $method"),
        };
    }

    /**
     * Search for similar vectors
     */
    public function search(
        array $queryVector,
        int $limit = 10,
        ?array $filter = null,
        float $scoreThreshold = 0.0
    ): array {
        try {
            $payload = [
                'vector' => [
                    'name' => 'nombix',
                    'vector' => $queryVector,
                ],
                'limit' => $limit,
                'with_payload' => true,
                'with_vector' => false,
            ];

            if ($filter) {
                $payload['filter'] = $filter;
            }

            if ($scoreThreshold > 0) {
                $payload['score_threshold'] = $scoreThreshold;
            }

            $response = $this->makeRequest(
                'POST',
                "/collections/{$this->collectionName}/points/search",
                $payload
            );

            if ($response->successful()) {
                return $response->json('result', []);
            }

            Log::error("Search failed in Qdrant", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];

        } catch (Exception $e) {
            Log::error("Error searching in Qdrant: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Search by document ID
     */
    public function searchByDocument(
        array $queryVector,
        int $documentId,
        int $limit = 10
    ): array {
        return $this->search(
            [
                'name' => 'nombix',
                'vector' => $queryVector,
            ],
            $limit,
            [
                'must' => [
                    [
                        'key' => 'document_id',
                        'match' => ['value' => $documentId],
                    ],
                ],
            ]
        );
    }

    /**
     * Get a specific point by ID
     */
    public function getPoint(string $pointId): ?array
    {
        try {
            $response = $this->makeRequest(
                'GET',
                "/collections/{$this->collectionName}/points/{$pointId}"
            );

            if ($response->successful()) {
                return $response->json('result');
            }

            return null;

        } catch (Exception $e) {
            Log::error("Error getting point from Qdrant: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a point
     */
    public function deletePoint(string $pointId): bool
    {
        try {
            $response = $this->makeRequest(
                'POST',
                "/collections/{$this->collectionName}/points/delete",
                [
                    'points' => [$pointId],
                ]
            );

            return $response->successful();

        } catch (Exception $e) {
            Log::error("Error deleting point from Qdrant: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete all points for a document
     */
    public function deleteDocumentPoints(int $documentId): bool
    {
        try {
            $response = $this->makeRequest(
                'POST',
                "/collections/{$this->collectionName}/points/delete",
                [
                    'filter' => [
                        'must' => [
                            [
                                'key' => 'document_id',
                                'match' => ['value' => $documentId],
                            ],
                        ],
                    ],
                ]
            );

            return $response->successful();

        } catch (Exception $e) {
            Log::error("Error deleting document points from Qdrant: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get collection info
     */
    public function getCollectionInfo(): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/collections/{$this->collectionName}");

            if ($response->successful()) {
                return $response->json('result');
            }

            return null;

        } catch (Exception $e) {
            Log::error("Error getting collection info from Qdrant: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Count points in collection
     */
    public function countPoints(?array $filter = null): int
    {
        try {
            $payload = [];

            if ($filter) {
                $payload['filter'] = $filter;
            }

            $response = $this->makeRequest(
                'POST',
                "/collections/{$this->collectionName}/points/count",
                $payload
            );

            if ($response->successful()) {
                return $response->json('result.count', 0);
            }

            return 0;

        } catch (Exception $e) {
            Log::error("Error counting points in Qdrant: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Health check
     */
    public function healthCheck(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/healthz');
            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }
}