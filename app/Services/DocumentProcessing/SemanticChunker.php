<?php

namespace App\Services\DocumentProcessing;

/**
 * Semantic Chunker - creates context-aware chunks
 */
class SemanticChunker
{
    private int $targetChunkSize = 1000;
    private int $minChunkSize = 500;
    private int $maxChunkSize = 1500;
    private int $overlap = 200;

    public function chunk(string $text, array $structure, array $metadata): array
    {
        // Choose chunking strategy based on structure
        if ($structure['has_sections']) {
            return $this->chunkBySections($text, $metadata);
        }
        
        if ($structure['has_headings']) {
            return $this->chunkByHeadings($text, $metadata);
        }
        
        return $this->chunkByParagraphs($text, $metadata);
    }

    private function chunkBySections(string $text, array $metadata): array
    {
        // Split by section markers
        $sections = preg_split(
            '/(^|\n)(?:---\s*PAGE\s*\d+\s*---|CHAPTER|SECTION|PART)\s+[^\n]+/i',
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        
        $chunks = [];
        $currentChunk = '';
        $chunkIndex = 0;
        
        foreach ($sections as $section) {
            $section = trim($section);
            if (empty($section)) continue;
            
            if (strlen($currentChunk . $section) > $this->maxChunkSize && !empty($currentChunk)) {
                $chunks[] = $this->createChunk(
                    $currentChunk,
                    $metadata,
                    $chunkIndex++,
                    'section_based'
                );
                
                // Keep overlap
                $currentChunk = $this->getOverlap($currentChunk) . "\n\n" . $section;
            } else {
                $currentChunk .= "\n\n" . $section;
            }
        }
        
        if (!empty(trim($currentChunk))) {
            $chunks[] = $this->createChunk(
                $currentChunk,
                $metadata,
                $chunkIndex,
                'section_based'
            );
        }
        
        return $chunks;
    }

    private function chunkByHeadings(string $text, array $metadata): array
    {
        $chunks = [];
        $lines = explode("\n", $text);
        $currentChunk = '';
        $currentHeading = '';
        $chunkIndex = 0;
        
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            
            // Detect heading
            if ($this->isHeadingLine($trimmedLine)) {
                // Save previous chunk if it exists
                if (!empty($currentChunk) && strlen($currentChunk) > $this->minChunkSize) {
                    $chunks[] = $this->createChunk(
                        $currentHeading . "\n\n" . $currentChunk,
                        $metadata,
                        $chunkIndex++,
                        'heading_based',
                        ['heading' => $currentHeading]
                    );
                    
                    // Start new chunk with overlap
                    $currentChunk = $this->getOverlap($currentChunk);
                } elseif (!empty($currentChunk)) {
                    $currentChunk .= "\n" . $line;
                    continue;
                }
                
                $currentHeading = $trimmedLine;
                $currentChunk = '';
            } else {
                $currentChunk .= "\n" . $line;
                
                // If chunk is getting too large, split even without heading
                if (strlen($currentChunk) > $this->maxChunkSize) {
                    $chunks[] = $this->createChunk(
                        $currentHeading . "\n\n" . $currentChunk,
                        $metadata,
                        $chunkIndex++,
                        'heading_based',
                        ['heading' => $currentHeading]
                    );
                    
                    $currentChunk = $this->getOverlap($currentChunk);
                }
            }
        }
        
        // Add final chunk
        if (!empty(trim($currentChunk))) {
            $chunks[] = $this->createChunk(
                $currentHeading . "\n\n" . $currentChunk,
                $metadata,
                $chunkIndex,
                'heading_based',
                ['heading' => $currentHeading]
            );
        }
        
        return $chunks;
    }

    private function chunkByParagraphs(string $text, array $metadata): array
    {
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $chunks = [];
        $currentChunk = '';
        $chunkIndex = 0;
        
        // Add document context to first chunk
        $contextPrefix = $this->buildContextPrefix($metadata);
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;
            
            $potentialChunk = empty($currentChunk) 
                ? $paragraph 
                : $currentChunk . "\n\n" . $paragraph;
            
            if (strlen($potentialChunk) > $this->maxChunkSize && !empty($currentChunk)) {
                // Save current chunk
                $finalChunk = $chunkIndex === 0 
                    ? $contextPrefix . $currentChunk 
                    : $currentChunk;
                    
                $chunks[] = $this->createChunk(
                    $finalChunk,
                    $metadata,
                    $chunkIndex++,
                    'paragraph_based'
                );
                
                // Start new chunk with overlap
                $currentChunk = $this->getOverlap($currentChunk) . "\n\n" . $paragraph;
            } else {
                $currentChunk = $potentialChunk;
            }
        }
        
        // Add final chunk
        if (!empty(trim($currentChunk))) {
            $finalChunk = $chunkIndex === 0 
                ? $contextPrefix . $currentChunk 
                : $currentChunk;
                
            $chunks[] = $this->createChunk(
                $finalChunk,
                $metadata,
                $chunkIndex,
                'paragraph_based'
            );
        }
        
        return $chunks;
    }

    private function isHeadingLine(string $line): bool
    {
        // Markdown headings
        if (preg_match('/^#{1,6}\s+.+$/', $line)) {
            return true;
        }
        
        // All caps lines
        if (strlen($line) > 10 && strlen($line) < 100 && $line === strtoupper($line)) {
            return true;
        }
        
        // Numbered sections
        if (preg_match('/^\d+\.\s+[A-Z].{5,}$/', $line)) {
            return true;
        }
        
        return false;
    }

    private function getOverlap(string $text): string
    {
        // Get last N characters as overlap
        $text = trim($text);
        
        if (strlen($text) <= $this->overlap) {
            return $text;
        }
        
        // Try to break at sentence
        $overlapText = mb_substr($text, -$this->overlap);
        $lastPeriod = mb_strrpos($overlapText, '.');
        
        if ($lastPeriod !== false && $lastPeriod > $this->overlap / 2) {
            return mb_substr($overlapText, $lastPeriod + 1);
        }
        
        return $overlapText;
    }

    private function buildContextPrefix(array $metadata): string
    {
        $parts = [];
        
        if (!empty($metadata['title']) && $metadata['title'] !== 'Untitled') {
            $parts[] = "Document: {$metadata['title']}";
        }
        
        if (!empty($metadata['author']) && $metadata['author'] !== 'Unknown') {
            $parts[] = "Author: {$metadata['author']}";
        }
        
        if (!empty($metadata['type'])) {
            $parts[] = "Type: {$metadata['type']}";
        }
        
        return empty($parts) ? '' : implode(" | ", $parts) . "\n\n---\n\n";
    }

    private function createChunk(
        string $text, 
        array $metadata, 
        int $index, 
        string $method,
        array $extra = []
    ): array {
        return [
            'text' => trim($text),
            'index' => $index,
            'char_count' => strlen($text),
            'word_count' => str_word_count($text),
            'metadata' => array_merge([
                'document_title' => $metadata['title'] ?? 'Untitled',
                'document_type' => $metadata['type'] ?? 'unknown',
                'chunking_method' => $method,
            ], $extra),
        ];
    }
}
