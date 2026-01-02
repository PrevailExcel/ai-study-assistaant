<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Models\Document;
use App\Services\ChromaService;
use App\Services\MultimediaFileProcessor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
        private ChromaService $chromaService;
    private MultimediaFileProcessor $fileProcessor;

    public function __construct(ChromaService $chromaService, MultimediaFileProcessor $fileProcessor)
    {
        $this->chromaService = $chromaService;
        $this->fileProcessor = $fileProcessor;

        // Initialize ChromaDB collection
        if ($this->chromaService->createDatabase()) {
            $this->chromaService->initializeCollection();
        }
    }

    /**
     * Upload and process various file types
     */
    public function uploadFile(Request $request) //: JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:51200', // 50MB max
            'file_type' => 'sometimes|string|in:pdf,pptx,ppt,docx,doc,mp4,mov,avi,mp3,wav',
            'name' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $uploadedFile = $request->file('file');
            $fileExtension = $uploadedFile->getClientOriginalExtension();
            $fileType = $request->input('file_type', $fileExtension);

            // Store the file
            $filePath = $uploadedFile->store('uploads');
            $fullPath = storage_path('app/private/' . $filePath);

            // Process the file based on type
            $processedContent = $this->fileProcessor->processFile($fullPath, $fileType);

            // Generate document ID
            $documentId = uniqid('doc_');

            logger()->info("📁 File uploaded and processed", [
                'document_id' => $documentId,
                'file_type' => $fileType,
                'file_path' => $filePath,
                'processed_content_summary' => $this->generateContentSummary($processedContent)
            ]);

            // Store in ChromaDB
            $success = $this->storeInVectorDB($documentId, $processedContent, [
                'filename' => $uploadedFile->getClientOriginalName(),
                'file_type' => $fileType,
                'upload_time' => now()->toISOString()
            ]);

            if (!$success) {
                return $this->error('Failed to store processed content', 500);
            }

            // Clean up temporary files
            $this->cleanupTempFiles();

            // save in the database
            $document = Document::create([
                'title' => $request->name ?? pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME),
                'doc_id' => $documentId,
                'file_path' => $filePath,
                'file_type' => $fileType,
                'user_id' => $request->user()->id,
                'metadata' => [
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'size' => $uploadedFile->getSize(),
                    'mime_type' => $uploadedFile->getMimeType()
                ]
            ]);

            return response()->json([
                'success' => true,
                'document_id' => $documentId,
                'file_type' => $fileType,
                'content_summary' => $this->generateContentSummary($processedContent),
                'processing_stats' => $this->getProcessingStats($processedContent),
                'document' => $document
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'File processing failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}