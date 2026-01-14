<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\{AIService, QuadrantService};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class GenerateEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $documentId;
    public int $timeout = 600;
    public int $tries = 3;

    public function __construct(string $documentId)
    {
        $this->documentId = $documentId;
    }

    public function handle(AIService $embeddingService, QuadrantService $qdrantService): void
    {
        $document = Document::with('chunks')->findOrFail($this->documentId);

        Log::info("Starting embeddings generation", [
            'document_id' => $this->documentId,
            'chunks_count' => $document->chunks->count(),
        ]);

        try {
            // Ensure Qdrant collection exists
            $qdrantService->initializeCollection();

            $chunks = $document->chunks;
            $batchSize = 100; // safe batch size
            $batches = $chunks->chunk($batchSize);
            $totalProcessed = 0;

            foreach ($batches as $batchIndex => $batch) {
                Log::info("Processing batch", [
                    'document_id' => $this->documentId,
                    'batch' => $batchIndex + 1,
                    'chunks_in_batch' => $batch->count(),
                ]);

                // Generate embeddings using Ollama Nombix
                $texts = $batch->pluck('content')->toArray();
                $embeddings = $embeddingService->ollamaBatch($texts); // returns array of 1536-dim vectors

                if (!$embeddings || count($embeddings) !== count($batch)) {
                    throw new Exception("Failed to generate embeddings for batch " . ($batchIndex + 1));
                }

                // Prepare points for Qdrant
                $points = [];
                foreach ($batch->values() as $index => $chunk) {

                    $pointId = (string) Str::uuid();

                    $points[] = [
                        'id' => $pointId,
                        'vector' => $embeddings[$index], // QuadrantService will wrap it with 'nombix'
                        'payload' => [
                            'document_id' => $document->id,
                            'chunk_id' => $chunk->id,
                            'chunk_index' => $chunk->chunk_index,
                            'content' => $chunk->content,
                            'char_count' => $chunk->char_count,
                            'bot_id' => $document->bot_id,
                            'word_count' => $chunk->word_count,
                            'metadata' => $chunk->metadata,
                            'document_metadata' => [
                                'title' => $document->metadata['title'] ?? 'Untitled',
                                'type' => $document->metadata['type'] ?? 'unknown',
                            ],
                            'created_at' => $chunk->created_at->toISOString(),
                        ],
                    ];

                    // Save Qdrant point ID on chunk
                    $chunk->update(['qdrant_point_id' => $pointId]);
                }

                // Store batch in Qdrant
                $success = $qdrantService->storeBatch($points);
                if (!$success) {
                    throw new Exception("Failed to store batch in Qdrant");
                }

                $totalProcessed += count($points);
                Log::info("Batch stored successfully", [
                    'document_id' => $this->documentId,
                    'batch' => $batchIndex + 1,
                    'points' => count($points),
                ]);

                // Optional: rate limit
                if ($batchIndex < $batches->count() - 1) {
                    sleep(1);
                }
            }

            // Update document metadata
            $document->update([
                'metadata' => array_merge($document->metadata ?? [], [
                    'embeddings_generated' => true,
                    'embeddings_count' => $totalProcessed,
                    'embeddings_generated_at' => now()->toISOString(),
                    'qdrant_collection' => config('services.quadrant.collection'),
                ]),
                'status' => 'processed',
            ]);

            Log::info("Embeddings generation completed", [
                'document_id' => $this->documentId,
                'total_embeddings' => $totalProcessed,
            ]);

        } catch (Exception $e) {
            Log::error("Embeddings generation failed", [
                'document_id' => $this->documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $document->update([
                'metadata' => array_merge($document->metadata ?? [], [
                    'embeddings_error' => $e->getMessage(),
                ]),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Embeddings generation job failed permanently", [
            'document_id' => $this->documentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
