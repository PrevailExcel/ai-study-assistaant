<?php

namespace App\Services\DocumentProcessing;

use Illuminate\Support\Facades\{Storage, Log};
use Exception;


/**
 * OCR Handler using Tesseract
 */
class OCRHandler
{
    private string $tesseractPath;
    private string $tempDir;
    
    public function __construct()
    {
        $this->tesseractPath = env('TESSERACT_PATH', '/usr/bin/tesseract');
        $this->tempDir = storage_path('app/temp/ocr');
        
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    public function processPdf(string $pdfPath): string
    {
        // Check if required tools are installed
        if (!$this->checkDependencies()) {
            throw new Exception(
                "OCR dependencies not installed. Please install:\n" .
                "- Tesseract OCR: apt-get install tesseract-ocr\n" .
                "- Poppler utils: apt-get install poppler-utils"
            );
        }
        
        // Convert PDF pages to images
        $imageFiles = $this->pdfToImages($pdfPath);
        
        $fullText = '';
        
        foreach ($imageFiles as $pageNum => $imagePath) {
            Log::info("OCR processing page " . ($pageNum + 1));
            
            try {
                $pageText = $this->ocrImage($imagePath);
                $fullText .= "\n\n--- PAGE " . ($pageNum + 1) . " (OCR) ---\n\n";
                $fullText .= $pageText;
            } catch (Exception $e) {
                Log::warning("OCR failed for page " . ($pageNum + 1) . ": " . $e->getMessage());
                $fullText .= "\n\n[Page " . ($pageNum + 1) . " - OCR failed]\n\n";
            } finally {
                // Clean up image file
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
        }
        
        return $fullText;
    }

    private function pdfToImages(string $pdfPath): array
    {
        $outputPrefix = $this->tempDir . '/' . uniqid('pdf_page_');
        
        // Use pdftoppm to convert PDF to images
        $command = sprintf(
            'pdftoppm -png -r 300 %s %s',
            escapeshellarg($pdfPath),
            escapeshellarg($outputPrefix)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to convert PDF to images");
        }
        
        // Find all generated images
        $images = glob($outputPrefix . '-*.png');
        sort($images);
        
        return $images;
    }

    private function ocrImage(string $imagePath): string
    {
        $outputFile = $this->tempDir . '/' . uniqid('ocr_') . '.txt';
        
        // Run tesseract
        $command = sprintf(
            '%s %s %s -l eng',
            escapeshellarg($this->tesseractPath),
            escapeshellarg($imagePath),
            escapeshellarg(pathinfo($outputFile, PATHINFO_FILENAME))
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($outputFile)) {
            throw new Exception("Tesseract OCR failed");
        }
        
        $text = file_get_contents($outputFile);
        unlink($outputFile);
        
        return $text;
    }

    private function checkDependencies(): bool
    {
        $tesseractExists = file_exists($this->tesseractPath) || 
                          !empty(shell_exec('which tesseract 2>/dev/null'));
        $popplerExists = !empty(shell_exec('which pdftoppm 2>/dev/null'));
        
        return $tesseractExists && $popplerExists;
    }
}

