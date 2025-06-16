<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Smalot\PdfParser\Parser;

class MultimediaPdfProcessor
{
    private ChromaService $chromaService;
    
    public function __construct(ChromaService $chromaService)
    {
        $this->chromaService = $chromaService;
    }

    /**
     * Process PDF with text, images, and visual elements
     */
    public function processPdf(string $pdfPath): array
    {
        $results = [
            'text_chunks' => [],
            'image_descriptions' => [],
            'ocr_text' => [],
            'combined_content' => []
        ];

        // 1. Extract regular text
        $textContent = $this->extractTextFromPdf($pdfPath);
        $results['text_chunks'] = $this->chunkText($textContent);

        // 2. Extract images from PDF
        $images = $this->extractImagesFromPdf($pdfPath);
        
        foreach ($images as $index => $imagePath) {
            // OCR for text in images
            $ocrText = $this->performOCR($imagePath);
            if (!empty($ocrText)) {
                $results['ocr_text'][] = [
                    'image_index' => $index,
                    'text' => $ocrText
                ];
            }

            // Visual analysis with Claude
            $imageDescription = $this->analyzeImageWithClaude($imagePath);
            $results['image_descriptions'][] = [
                'image_index' => $index,
                'description' => $imageDescription
            ];
        }

        // 3. Combine all content for comprehensive chunks
        $results['combined_content'] = $this->createCombinedChunks($results);

        return $results;
    }

    private function extractImagesFromPdf(string $pdfPath): array
    {
        // Using ImageMagick or similar to extract images
        $outputDir = storage_path('app/temp/pdf_images/');
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Convert PDF pages to images
        $command = "convert -density 150 '{$pdfPath}' -quality 90 '{$outputDir}page_%03d.jpg'";
        exec($command);

        // Get extracted image files
        $images = glob($outputDir . '*.jpg');
        return $images;
    }

    private function performOCR(string $imagePath): string
    {
        try {
            return (new TesseractOCR($imagePath))
                ->lang('eng')
                ->configFile('pdf')
                ->run();
        } catch (\Exception $e) {
            return '';
        }
    }

    private function analyzeImageWithClaude(string $imagePath): string
    {
        try {
            // Convert image to base64
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath);

            $response = Http::withHeaders([
                'x-api-key' => config('anthropic.api_key'),
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-3-sonnet-20240229',
                'max_tokens' => 1000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Analyze this image from a study material. Describe any charts, diagrams, equations, important visual information, or text that would be relevant for studying. Be detailed and educational.'
                            ],
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mimeType,
                                    'data' => $imageData
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                return $response->json()['content'][0]['text'];
            }
        } catch (\Exception $e) {
            return 'Failed to analyze image: ' . $e->getMessage();
        }

        return 'Unable to analyze image';
    }

    private function createCombinedChunks(array $results): array
    {
        $combinedChunks = [];
        
        // Interleave text chunks with image content
        $textChunks = $results['text_chunks'];
        $imageDescriptions = $results['image_descriptions'];
        $ocrTexts = $results['ocr_text'];
        
        foreach ($textChunks as $index => $textChunk) {
            $combined = $textChunk;
            
            // Add related image descriptions
            if (isset($imageDescriptions[$index])) {
                $combined .= "\n\n[IMAGE DESCRIPTION]: " . $imageDescriptions[$index]['description'];
            }
            
            // Add OCR text from images
            if (isset($ocrTexts[$index]) && !empty($ocrTexts[$index]['text'])) {
                $combined .= "\n\n[TEXT FROM IMAGE]: " . $ocrTexts[$index]['text'];
            }
            
            $combinedChunks[] = $combined;
        }
        
        return $combinedChunks;
    }

    private function extractTextFromPdf(string $pdfPath): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($pdfPath);
        return $pdf->getText();
    }

    private function chunkText(string $text, int $chunkSize = 1000): array
    {
        // Same chunking logic as before
        $chunks = [];
        $textLength = strlen($text);
        
        for ($i = 0; $i < $textLength; $i += $chunkSize) {
            $chunk = substr($text, $i, $chunkSize);
            $chunks[] = trim($chunk);
        }
        
        return array_filter($chunks);
    }
}