<?php

namespace App\Services\DocumentProcessing;

/**
 * Structure Detector - analyzes document structure
 */
class StructureDetector
{
    public function analyze(string $text): array
    {
        return [
            'has_headings' => $this->detectHeadings($text),
            'has_lists' => $this->detectLists($text),
            'has_tables' => $this->detectTables($text),
            'has_sections' => $this->detectSections($text),
            'has_code_blocks' => $this->detectCodeBlocks($text),
            'paragraph_count' => $this->countParagraphs($text),
            'heading_hierarchy' => $this->analyzeHeadingHierarchy($text),
            'document_type' => $this->guessDocumentType($text),
        ];
    }

    private function detectHeadings(string $text): bool
    {
        // Markdown headings
        if (preg_match('/^#{1,6}\s+.+$/m', $text)) {
            return true;
        }
        
        // All-caps lines (potential headings)
        if (preg_match('/^[A-Z][A-Z\s]{10,}$/m', $text)) {
            return true;
        }
        
        // Numbered sections
        if (preg_match('/^\d+\.\s+[A-Z].{10,}$/m', $text)) {
            return true;
        }
        
        return false;
    }

    private function detectLists(string $text): bool
    {
        return (bool) preg_match('/^[\s]*[\-\*\•]\s+/m', $text) ||
               (bool) preg_match('/^[\s]*\d+\.\s+/m', $text);
    }

    private function detectTables(string $text): bool
    {
        return (bool) preg_match('/\[TABLE START\]/', $text) ||
               (bool) preg_match('/\|.*\|.*\|/', $text);
    }

    private function detectSections(string $text): bool
    {
        return (bool) preg_match('/(?:chapter|section|part|appendix)\s+\d+/i', $text);
    }

    private function detectCodeBlocks(string $text): bool
    {
        return (bool) preg_match('/```[\s\S]*?```/', $text) ||
               (bool) preg_match('/^    \w+/m', $text);
    }

    private function countParagraphs(string $text): int
    {
        $paragraphs = preg_split('/\n\s*\n/', $text);
        return count(array_filter($paragraphs, fn($p) => strlen(trim($p)) > 50));
    }

    private function analyzeHeadingHierarchy(string $text): array
    {
        $hierarchy = [];
        
        // Markdown headings
        if (preg_match_all('/^(#{1,6})\s+(.+)$/m', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $level = strlen($match[1]);
                $hierarchy[] = ['level' => $level, 'text' => trim($match[2])];
            }
        }
        
        return $hierarchy;
    }

    private function guessDocumentType(string $text): string
    {
        if (preg_match('/abstract|introduction|methodology|conclusion|references/i', $text)) {
            return 'academic_paper';
        }
        
        if (preg_match('/executive summary|overview|objectives/i', $text)) {
            return 'business_report';
        }
        
        if (preg_match('/function|class|def |public |private |const /i', $text)) {
            return 'technical_documentation';
        }
        
        if (preg_match('/chapter \d+|table of contents/i', $text)) {
            return 'book';
        }
        
        return 'general';
    }
}
