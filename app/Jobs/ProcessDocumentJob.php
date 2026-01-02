<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentProcessing\DocumentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\{Storage, Log, DB};
use Exception;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $documentId;
    public int $timeout = 600; // 10 minutes for large documents with OCR
    public int $tries = 3;
    public int $maxExceptions = 2;

    public function __construct(int $documentId)
    {
        $this->documentId = $documentId;
    }

    public function handle(DocumentProcessor $processor): void
    {
        $document = Document::findOrFail($this->documentId);
        
        Log::info("Starting document processing", [
            'document_id' => $this->documentId,
            'file_path' => $document->file_path,
        ]);

        try {
            // Update status
            $document->update([
                'status' => 'processing',
                'processing_started_at' => now(),
            ]);

            // Validate file exists
            if (!Storage::exists($document->file_path)) {
                throw new Exception("File not found: {$document->file_path}");
            }

            // Get file info
            $filePath = Storage::path($document->file_path);
            $mimeType = Storage::mimeType($document->file_path);
            $fileSize = Storage::size($document->file_path);

            // Check file size limit (50MB default)
            $maxSize = config('documents.max_file_size', 50 * 1024 * 1024);
            if ($fileSize > $maxSize) {
                throw new Exception(
                    "File too large: " . round($fileSize / 1024 / 1024, 2) . "MB. " .
                    "Maximum allowed: " . round($maxSize / 1024 / 1024, 2) . "MB"
                );
            }

            Log::info("Processing document with enhanced processor", [
                'document_id' => $this->documentId,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
            ]);

            // Process document with full structure preservation
            $processed = $processor->process($filePath, $mimeType);
            $result = $processed->toArray();

            Log::info("Document processed successfully", [
                'document_id' => $this->documentId,
                'total_chars' => $result['stats']['total_chars'],
                'total_chunks' => $result['stats']['total_chunks'],
                'processing_method' => $result['metadata']['processing_method'],
                'quality' => $result['metadata']['quality'],
            ]);

            // Save full text and metadata
            DB::transaction(function () use ($document, $result) {
                $document->update([
                    'processed_text' => $result['full_text'],
                    'metadata' => array_merge(
                        $document->metadata ?? [],
                        $result['metadata'],
                        ['structure' => $result['structure']]
                    ),
                    'total_chunks' => $result['stats']['total_chunks'],
                    'processing_completed_at' => now(),
                ]);

                // Store chunks for RAG/embeddings
                foreach ($result['chunks'] as $chunk) {
                    $document->chunks()->create([
                        'content' => $chunk['text'],
                        'chunk_index' => $chunk['index'],
                        'char_count' => $chunk['char_count'],
                        'word_count' => $chunk['word_count'],
                        'metadata' => $chunk['metadata'],
                    ]);
                }
            });

            // Dispatch embedding generation
            GenerateEmbeddingsJob::dispatch($document->id);

            // Update final status
            $document->update([
                'status' => 'processed',
                'processing_completed_at' => now(),
                'error_message' => null,
            ]);

            // Log warnings if any
            if (!empty($result['metadata']['warning'])) {
                Log::warning("Document processing completed with warnings", [
                    'document_id' => $this->documentId,
                    'warning' => $result['metadata']['warning'],
                ]);
            }

        } catch (Exception $e) {
            Log::error("Document processing failed", [
                'document_id' => $this->documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processing_completed_at' => now(),
            ]);

            // Re-throw for retry mechanism
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error("Document processing job failed permanently", [
            'document_id' => $this->documentId,
            'error' => $exception->getMessage(),
        ]);

        $document = Document::find($this->documentId);
        if ($document) {
            $document->update([
                'status' => 'failed',
                'error_message' => 'Processing failed after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
            ]);
        }
    }
}