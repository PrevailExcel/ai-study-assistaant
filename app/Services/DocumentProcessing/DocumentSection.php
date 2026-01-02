<?php

namespace App\Services\DocumentProcessing;


/**
 * Document Section
 */
class DocumentSection
{
    public string $name;
    public array $headers = [];
    public array $headings = [];
    public array $paragraphs = [];
    public array $tables = [];
    public array $images = [];
    public array $footers = [];
    
    public function __construct(string $name)
    {
        $this->name = $name;
    }
    
    public function addHeader(string $text): void
    {
        $this->headers[] = $text;
    }
    
    public function addHeading(string $text, int $level): void
    {
        $this->headings[] = ['text' => $text, 'level' => $level];
    }
    
    public function addParagraph(string $text, int $level): void
    {
        $this->paragraphs[] = ['text' => $text, 'level' => $level];
    }
    
    public function addTable(string $content, int $level): void
    {
        $this->tables[] = ['content' => $content, 'level' => $level];
    }
    
    public function addImage(string $placeholder, int $level): void
    {
        $this->images[] = ['placeholder' => $placeholder, 'level' => $level];
    }
    
    public function addFooter(string $text): void
    {
        $this->footers[] = $text;
    }
    
    public function toText(): string
    {
        $text = "### {$this->name} ###\n\n";
        
        // Headers
        foreach ($this->headers as $header) {
            $text .= "[HEADER] $header\n";
        }
        
        if (!empty($this->headers)) {
            $text .= "\n";
        }
        
        // Interleave headings, paragraphs, tables, images in order
        $allContent = array_merge(
            array_map(fn($h) => ['type' => 'heading', 'data' => $h], $this->headings),
            array_map(fn($p) => ['type' => 'paragraph', 'data' => $p], $this->paragraphs),
            array_map(fn($t) => ['type' => 'table', 'data' => $t], $this->tables),
            array_map(fn($i) => ['type' => 'image', 'data' => $i], $this->images)
        );
        
        foreach ($allContent as $item) {
            $indent = str_repeat('  ', $item['data']['level'] ?? 0);
            
            switch ($item['type']) {
                case 'heading':
                    $text .= "\n$indent## {$item['data']['text']} ##\n\n";
                    break;
                case 'paragraph':
                    $text .= "$indent{$item['data']['text']}\n\n";
                    break;
                case 'table':
                    $text .= "$indent{$item['data']['content']}\n";
                    break;
                case 'image':
                    $text .= "$indent{$item['data']['placeholder']}\n\n";
                    break;
            }
        }
        
        // Footers
        if (!empty($this->footers)) {
            $text .= "\n";
            foreach ($this->footers as $footer) {
                $text .= "[FOOTER] $footer\n";
            }
        }
        
        return $text;
    }
}