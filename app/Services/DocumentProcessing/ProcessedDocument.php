<?php

namespace App\Services\DocumentProcessing;


/**
 * Processed Document Result
 */
class ProcessedDocument
{
    public string $fullText;
    public array $chunks;
    public array $metadata;
    public array $structure;
    
    public function __construct(
        string $fullText,
        array $chunks,
        array $metadata,
        array $structure
    ) {
        $this->fullText = $fullText;
        $this->chunks = $chunks;
        $this->metadata = $metadata;
        $this->structure = $structure;
    }
    
    public function toArray(): array
    {
        return [
            'full_text' => $this->fullText,
            'chunks' => $this->chunks,
            'metadata' => $this->metadata,
            'structure' => $this->structure,
            'stats' => [
                'total_chars' => strlen($this->fullText),
                'total_words' => str_word_count($this->fullText),
                'total_chunks' => count($this->chunks),
                'avg_chunk_size' => count($this->chunks) > 0 
                    ? array_sum(array_column($this->chunks, 'char_count')) / count($this->chunks)
                    : 0,
            ],
        ];
    }
}
