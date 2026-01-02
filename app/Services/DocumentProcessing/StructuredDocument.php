<?php

namespace App\Services\DocumentProcessing;


/**
 * Structured Document Builder
 */
class StructuredDocument
{
    public array $sections = [];
    
    public function addSection(DocumentSection $section): void
    {
        $this->sections[] = $section;
    }
    
    public function toText(): string
    {
        $text = '';
        
        foreach ($this->sections as $index => $section) {
            if ($index > 0) {
                $text .= "\n\n=== SECTION BREAK ===\n\n";
            }
            $text .= $section->toText();
        }
        
        return $text;
    }
    
    public function getStructureSummary(): array
    {
        $summary = [
            'total_sections' => count($this->sections),
            'total_headings' => 0,
            'total_paragraphs' => 0,
            'total_tables' => 0,
        ];
        
        foreach ($this->sections as $section) {
            $summary['total_headings'] += count($section->headings);
            $summary['total_paragraphs'] += count($section->paragraphs);
            $summary['total_tables'] += count($section->tables);
        }
        
        return $summary;
    }
}
