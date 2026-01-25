<?php

namespace App\Services\DocumentProcessing;

use Illuminate\Support\Facades\Log;
use Exception;

class OCRHandler
{
    private string $tempDir;

    public function __construct()
    {
        $this->tempDir = storage_path('app/temp/ocr');

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Main method to process a PDF and return its extracted text.
     */
    public function processPdf(string $pdfPath): string
    {
        // Generate temporary OCR output PDF
        $outputPdf = $this->tempDir . '/' . uniqid('ocr_') . '.pdf';

        try {
            // Try OCRmyPDF first
            $this->runOcrmypdf($pdfPath, $outputPdf);

            // Extract text from the OCR'd PDF
            $text = $this->extractTextFromPdf($outputPdf);

            return $text ?: '';
        } catch (Exception $e) {
            Log::warning("OCRmyPDF failed, falling back to Tesseract: " . $e->getMessage());

            // Fallback: manual page-by-page Tesseract OCR
            return $this->fallbackTesseract($pdfPath);
        } finally {
            // Cleanup temp OCR PDF
            if (file_exists($outputPdf)) {
                unlink($outputPdf);
            }
        }
    }

    /**
     * Run OCRmyPDF on the given PDF.
     */
    private function runOcrmypdf(string $inputPdf, string $outputPdf): void
    {
        $command = sprintf(
            'ocrmypdf -l eng --force-ocr --deskew --remove-background %s %s 2>&1',
            escapeshellarg($inputPdf),
            escapeshellarg($outputPdf)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        Log::debug('OCRmyPDF command executed', [
            'command' => $command,
            'return_code' => $returnCode,
            'output' => $output,
        ]);

        if ($returnCode !== 0 || !file_exists($outputPdf)) {
            throw new Exception("OCRmyPDF failed:\n" . implode("\n", $output));
        }
    }

    /**
     * Extract text from PDF using pdftotext.
     */
    private function extractTextFromPdf(string $pdfPath): string
    {
        $text = shell_exec("pdftotext " . escapeshellarg($pdfPath) . " -");
        return $text ?: '';
    }

    /**
     * Fallback OCR using manual Tesseract per page.
     */
    private function fallbackTesseract(string $pdfPath): string
    {
        // Convert PDF pages to images using pdftoppm
        $outputPrefix = $this->tempDir . '/' . uniqid('pdf_page_');

        $command = sprintf(
            'pdftoppm -png -r 300 %s %s 2>&1',
            escapeshellarg($pdfPath),
            escapeshellarg($outputPrefix)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        Log::debug('pdftoppm command', [
            'command' => $command,
            'return_code' => $returnCode,
            'output' => $output,
        ]);

        if ($returnCode !== 0) {
            throw new Exception("Failed to convert PDF to images:\n" . implode("\n", $output));
        }

        $images = glob($outputPrefix . '-*.png');
        sort($images);

        $fullText = '';

        foreach ($images as $pageNum => $imagePath) {
            $fullText .= "\n\n--- PAGE " . ($pageNum + 1) . " (OCR fallback) ---\n\n";
            try {
                $fullText .= $this->ocrImage($imagePath);
            } catch (Exception $e) {
                Log::warning("Tesseract fallback failed for page " . ($pageNum + 1) . ": " . $e->getMessage());
                $fullText .= "[Page " . ($pageNum + 1) . " - OCR failed]\n";
            } finally {
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
        }

        return $fullText;
    }

    /**
     * Run Tesseract OCR on a single image.
     */
    private function ocrImage(string $imagePath): string
    {
        $outputBase = $this->tempDir . '/' . uniqid('ocr_');
        $command = sprintf(
            'tesseract %s %s -l eng 2>&1',
            escapeshellarg($imagePath),
            escapeshellarg($outputBase)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        $outputFile = $outputBase . '.txt';

        Log::debug('Tesseract command', [
            'command' => $command,
            'return_code' => $returnCode,
            'output' => $output,
        ]);

        if ($returnCode !== 0 || !file_exists($outputFile)) {
            throw new Exception("Tesseract OCR failed:\n" . implode("\n", $output));
        }

        $text = file_get_contents($outputFile);
        unlink($outputFile);

        return $text;
    }
}
