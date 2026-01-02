<?php

namespace App\Services\DocumentProcessing;

use Smalot\PdfParser\Parser as SmalotPdfParser;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\{AbstractElement, Text, TextRun, Table, Image as WordImage};
use Illuminate\Support\Facades\{Storage, Log};
use Exception;

/**
 * Complete Document Processor with maximum accuracy
 * Handles PDFs, DOCX, TXT with structure preservation
 * 
 * Required packages:
 * composer require smalot/pdfparser
 * composer require phpoffice/phpword
 * composer require thiagoalessio/tesseract_ocr (for OCR)
 * 
 * System requirements:
 * - Tesseract OCR: apt-get install tesseract-ocr
 * - Poppler utils: apt-get install poppler-utils (for pdf-to-image conversion)
 * - ImageMagick: apt-get install imagemagick (for image processing)
 */
class DocumentProcessor
{
    private OCRHandler $ocrHandler;
    private StructureDetector $structureDetector;
    private SemanticChunker $chunker;
    
    public function __construct()
    {
        $this->ocrHandler = new OCRHandler();
        $this->structureDetector = new StructureDetector();
        $this->chunker = new SemanticChunker();
    }

    /**
     * Main entry point - extracts text with maximum context preservation
     */
    public function process(string $filePath, string $mimeType): ProcessedDocument
    {
        Log::info("Processing document: $filePath", ['mime' => $mimeType]);
        
        try {
            $result = match(true) {
                str_contains($mimeType, 'pdf') => $this->processPdf($filePath),
                str_contains($mimeType, 'word') || 
                str_contains($mimeType, 'document') => $this->processDocx($filePath),
                str_contains($mimeType, 'text') => $this->processText($filePath),
                str_contains($mimeType, 'markdown') => $this->processMarkdown($filePath),
                default => throw new Exception("Unsupported file type: {$mimeType}"),
            };
            
            Log::info("Document processed successfully", [
                'chars' => strlen($result->fullText),
                'chunks' => count($result->chunks)
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            Log::error("Document processing failed", [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Process PDF with multiple fallback strategies
     */
    private function processPdf(string $filePath): ProcessedDocument
    {
        $metadata = [
            'type' => 'pdf',
            'file_size' => filesize($filePath),
            'processing_method' => null,
        ];
        
        // Strategy 1: Try standard text extraction
        try {
            $parser = new SmalotPdfParser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            
            // Get metadata
            $details = $pdf->getDetails();
            $pages = $pdf->getPages();
            
            $metadata = array_merge($metadata, [
                'title' => $details['Title'] ?? 'Untitled',
                'author' => $details['Author'] ?? 'Unknown',
                'pages' => count($pages),
                'created' => $details['CreationDate'] ?? null,
                'producer' => $details['Producer'] ?? null,
            ]);
            
            // Check if extraction was successful
            $cleanText = trim(preg_replace('/\s+/', ' ', $text));
            $textDensity = strlen($cleanText) / max(count($pages), 1);
            
            if ($textDensity > 100) { // At least 100 chars per page average
                Log::info("PDF text extracted successfully", [
                    'pages' => count($pages),
                    'chars' => strlen($text),
                    'density' => $textDensity
                ]);
                
                $metadata['processing_method'] = 'text_extraction';
                $metadata['quality'] = 'high';
                
                // Enhance text structure
                $enhancedText = $this->enhancePdfText($text, $pages);
                
                return $this->createProcessedDocument($enhancedText, $metadata);
            }
            
            Log::warning("PDF text density too low, attempting OCR", [
                'density' => $textDensity
            ]);
            
        } catch (Exception $e) {
            Log::warning("PDF text extraction failed: " . $e->getMessage());
        }
        
        // Strategy 2: OCR fallback for scanned PDFs
        try {
            Log::info("Starting OCR processing for PDF");
            $text = $this->ocrHandler->processPdf($filePath);
            
            $metadata['processing_method'] = 'ocr';
            $metadata['quality'] = 'medium';
            $metadata['warning'] = 'Document was scanned - OCR used (may contain errors)';
            
            return $this->createProcessedDocument($text, $metadata);
            
        } catch (Exception $e) {
            throw new Exception("PDF processing failed with all methods: " . $e->getMessage());
        }
    }

    /**
     * Enhance PDF text by preserving structure across pages
     */
    private function enhancePdfText(string $text, array $pages): string
    {
        $enhanced = '';
        $pageNum = 1;
        
        foreach ($pages as $page) {
            $pageText = $page->getText();
            
            // Add page marker for context
            $enhanced .= "\n\n--- PAGE $pageNum ---\n\n";
            
            // Clean up common PDF artifacts
            $pageText = $this->cleanPdfText($pageText);
            
            $enhanced .= $pageText;
            $pageNum++;
        }
        
        return $enhanced;
    }

    /**
     * Clean common PDF extraction artifacts
     */
    private function cleanPdfText(string $text): string
    {
        // Remove weird spacing
        $text = preg_replace('/(\w)\s+(\w)/u', '$1$2', $text);
        
        // Fix broken words across lines
        $text = preg_replace('/(\w+)-\s+(\w+)/', '$1$2', $text);
        
        // Normalize whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Fix multiple newlines but preserve paragraph breaks
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        return $text;
    }

    /**
     * Process DOCX with complete structure preservation
     */
    private function processDocx(string $filePath): ProcessedDocument
    {
        $phpWord = IOFactory::load($filePath);
        
        $metadata = [
            'type' => 'docx',
            'title' => $phpWord->getDocInfo()->getTitle() ?? 'Untitled',
            'author' => $phpWord->getDocInfo()->getCreator() ?? 'Unknown',
            'created' => $phpWord->getDocInfo()->getCreated(),
            'modified' => $phpWord->getDocInfo()->getModified(),
            'processing_method' => 'structured_extraction',
            'quality' => 'high',
        ];
        
        $document = new StructuredDocument();
        
        foreach ($phpWord->getSections() as $sectionIndex => $section) {
            $sectionObj = new DocumentSection("Section " . ($sectionIndex + 1));
            
            // Extract headers
            foreach ($section->getHeaders() as $headerIndex => $header) {
                $headerText = $this->extractElementsWithStructure($header->getElements());
                if (!empty(trim($headerText))) {
                    $sectionObj->addHeader($headerText);
                }
            }
            
            // Extract main content with deep structure
            $this->extractSectionContent($section->getElements(), $sectionObj);
            
            // Extract footers
            foreach ($section->getFooters() as $footerIndex => $footer) {
                $footerText = $this->extractElementsWithStructure($footer->getElements());
                if (!empty(trim($footerText))) {
                    $sectionObj->addFooter($footerText);
                }
            }
            
            $document->addSection($sectionObj);
        }
        
        $fullText = $document->toText();
        $metadata['sections'] = count($document->sections);
        $metadata['structure'] = $document->getStructureSummary();
        
        return $this->createProcessedDocument($fullText, $metadata);
    }

    /**
     * Deep extraction of DOCX elements with structure
     */
    private function extractSectionContent(array $elements, DocumentSection $section, int $depth = 0): void
    {
        foreach ($elements as $element) {
            $elementClass = get_class($element);
            
            // Text and TextRun
            if ($element instanceof Text || $element instanceof TextRun) {
                $text = method_exists($element, 'getText') ? $element->getText() : '';
                
                if (!empty($text)) {
                    // Detect if it's a heading based on style
                    $isHeading = $this->isHeading($element);
                    
                    if ($isHeading) {
                        $section->addHeading($text, $depth);
                    } else {
                        $section->addParagraph($text, $depth);
                    }
                }
            }
            
            // Tables
            elseif ($element instanceof Table) {
                $tableContent = $this->extractTable($element);
                $section->addTable($tableContent, $depth);
            }
            
            // Lists and nested elements
            elseif (method_exists($element, 'getElements')) {
                $this->extractSectionContent($element->getElements(), $section, $depth + 1);
            }
            
            // Images (placeholder)
            elseif ($element instanceof WordImage) {
                $section->addImage('[Image: ' . $element->getSource() . ']', $depth);
            }
        }
    }

    /**
     * Check if element is a heading
     */
    private function isHeading($element): bool
    {
        if (method_exists($element, 'getFontStyle')) {
            $style = $element->getFontStyle();
            if ($style) {
                $size = is_object($style) && method_exists($style, 'getSize') 
                    ? $style->getSize() 
                    : null;
                $bold = is_object($style) && method_exists($style, 'getBold') 
                    ? $style->getBold() 
                    : false;
                    
                return ($size && $size > 14) || $bold;
            }
        }
        
        return false;
    }

    /**
     * Extract table with structure
     */
    private function extractTable(Table $table): string
    {
        $tableText = "\n[TABLE START]\n";
        
        foreach ($table->getRows() as $rowIndex => $row) {
            $rowCells = [];
            foreach ($row->getCells() as $cell) {
                $cellText = $this->extractElementsWithStructure($cell->getElements());
                $rowCells[] = trim($cellText);
            }
            
            if ($rowIndex === 0) {
                $tableText .= "Headers: " . implode(" | ", $rowCells) . "\n";
            } else {
                $tableText .= "Row $rowIndex: " . implode(" | ", $rowCells) . "\n";
            }
        }
        
        $tableText .= "[TABLE END]\n";
        
        return $tableText;
    }

    /**
     * Simple element extraction
     */
    private function extractElementsWithStructure(array $elements): string
    {
        $text = '';
        
        foreach ($elements as $element) {
            if (method_exists($element, 'getText')) {
                $text .= $element->getText() . ' ';
            } elseif (method_exists($element, 'getElements')) {
                $text .= $this->extractElementsWithStructure($element->getElements());
            }
        }
        
        return $text;
    }

    /**
     * Process plain text
     */
    private function processText(string $filePath): ProcessedDocument
    {
        $text = file_get_contents($filePath);
        
        $metadata = [
            'type' => 'text',
            'processing_method' => 'direct',
            'quality' => 'high',
            'encoding' => mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true),
        ];
        
        return $this->createProcessedDocument($text, $metadata);
    }

    /**
     * Process markdown with structure
     */
    private function processMarkdown(string $filePath): ProcessedDocument
    {
        $text = file_get_contents($filePath);
        
        $metadata = [
            'type' => 'markdown',
            'processing_method' => 'direct',
            'quality' => 'high',
            'has_structure' => true,
        ];
        
        // Markdown already has structure, just validate
        $structure = $this->structureDetector->analyze($text);
        $metadata['structure'] = $structure;
        
        return $this->createProcessedDocument($text, $metadata);
    }

    /**
     * Create final processed document with chunks
     */
    private function createProcessedDocument(string $text, array $metadata): ProcessedDocument
    {
        // Detect structure
        $structure = $this->structureDetector->analyze($text);
        
        // Create semantic chunks
        $chunks = $this->chunker->chunk($text, $structure, $metadata);
        
        return new ProcessedDocument($text, $chunks, $metadata, $structure);
    }
}
