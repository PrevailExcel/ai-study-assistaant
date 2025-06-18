<?php

namespace App\Http\Controllers;

use App\Agents\QuestionGeneratorAgent;
use App\Services\ChromaService;
use App\Services\MultimediaFileProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use NeuronAI\Chat\Messages\UserMessage;

class EnhancedStudyAssistantController extends Controller
{
    private ChromaService $chromaService;
    private MultimediaFileProcessor $fileProcessor;

    public function __construct(ChromaService $chromaService, MultimediaFileProcessor $fileProcessor)
    {
        $this->chromaService = $chromaService;
        $this->fileProcessor = $fileProcessor;

        // Initialize ChromaDB collection
        $this->chromaService->initializeCollection();
    }

    /**
     * Upload and process various file types
     */
    public function uploadFile(Request $request) //: JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:51200', // 50MB max
            'file_type' => 'sometimes|string|in:pdf,pptx,ppt,docx,doc,mp4,mov,avi,mp3,wav'
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

            // Store in ChromaDB
            $success = $this->storeInVectorDB($documentId, $processedContent, [
                'filename' => $uploadedFile->getClientOriginalName(),
                'file_type' => $fileType,
                'upload_time' => now()->toISOString()
            ]);

            if (!$success) {
                return response()->json(['error' => 'Failed to store processed content'], 500);
            }

            // Clean up temporary files
            $this->cleanupTempFiles();

            return response()->json([
                'success' => true,
                'document_id' => $documentId,
                'file_type' => $fileType,
                'content_summary' => $this->generateContentSummary($processedContent),
                'processing_stats' => $this->getProcessingStats($processedContent)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'File processing failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate multiple choice questions with multimedia context
     */
    public function generateQuestions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'required|string',
            'topic' => 'sometimes|string',
            'count' => 'required|integer|min:1|max:20',
            'difficulty' => 'sometimes|string|in:easy,medium,hard',
            'question_types' => 'sometimes|array',
            'question_types.*' => 'string|in:multiple_choice,true_false,fill_blank,short_answer',
            'include_visual_content' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $documentId = $request->input('document_id');
            $topic = $request->input('topic', 'main concepts and key points');
            $count = $request->input('count', 5);
            $difficulty = $request->input('difficulty', 'medium');
            $questionTypes = $request->input('question_types', ['multiple_choice']);
            $includeVisual = $request->input('include_visual_content', true);

            logger()->info("📝 Generating questions for document '{$documentId}'", [
                'topic' => $topic,
                'count' => $count,
                'difficulty' => $difficulty,
                'question_types' => $questionTypes,
                'include_visual_content' => $includeVisual
            ]);

            // Query relevant content from ChromaDB
            $relevantContent = $this->getRelevantContent($documentId, $topic, $includeVisual);

            logger()->info("🔍 Relevant content retrieved for topic '{$topic}'", [
                'document_id' => $documentId,
                'include_visual' => $includeVisual,
                'content_count' => count($relevantContent),
                'relevant_content' => $relevantContent
            ]);

            if (empty($relevantContent)) {
                return response()->json(['error' => 'No relevant content found'], 404);
            }



            // Generate questions using Anthropic with multimedia context
            $questions = $this->generateQuestionsWithContext(
                $relevantContent,
                $count,
                $difficulty,
                $questionTypes
            );

            return response()->json([
                'success' => true,
                'questions' => $questions,
                'content_sources' => $this->getContentSources($relevantContent),
                'generation_params' => [
                    'topic' => $topic,
                    'count' => $count,
                    'difficulty' => $difficulty,
                    'types' => $questionTypes
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Question generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate comprehensive summary with multimedia elements
     */
    public function generateSummary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'required|string',
            'summary_type' => 'sometimes|string|in:brief,detailed,key_points,visual_summary',
            'include_multimedia' => 'sometimes|boolean',
            'max_length' => 'sometimes|integer|min:100|max:5000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $documentId = $request->input('document_id');
            $summaryType = $request->input('summary_type', 'detailed');
            $includeMultimedia = $request->input('include_multimedia', true);
            $maxLength = $request->input('max_length', 2000);

            // Get all content for the document
            $allContent = $this->getAllDocumentContent($documentId, $includeMultimedia);

            if (empty($allContent)) {
                return response()->json(['error' => 'Document not found'], 404);
            }

            // Generate summary with multimedia context
            $summary = $this->generateSummaryWithContext($allContent, $summaryType, $maxLength);

            return response()->json([
                'success' => true,
                'summary' => $summary,
                'summary_type' => $summaryType,
                'content_stats' => $this->getContentStats($allContent),
                'multimedia_elements' => $this->getMultimediaElements($allContent)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Summary generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search across multimedia content
     */
    public function searchContent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:3',
            'document_ids' => 'sometimes|array',
            'content_types' => 'sometimes|array',
            'content_types.*' => 'string|in:text,image_description,ocr,transcript',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $query = $request->input('query');
            $documentIds = $request->input('document_ids', []);
            $contentTypes = $request->input('content_types', []);
            $limit = $request->input('limit', 10);

            // Search in ChromaDB
            $searchResults = $this->chromaService->queryDocuments($query, $limit);

            // Filter results if specific documents or content types requested
            $filteredResults = $this->filterSearchResults($searchResults, $documentIds, $contentTypes);

            return response()->json([
                'success' => true,
                'results' => $filteredResults,
                'total_results' => count($filteredResults),
                'query' => $query
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document information and metadata
     */
    public function getDocumentInfo(Request $request, string $documentId): JsonResponse
    {
        try {
            // Query all chunks for this document
            $documentContent = $this->getAllDocumentContent($documentId, true);

            if (empty($documentContent)) {
                return response()->json(['error' => 'Document not found'], 404);
            }

            // Analyze document structure
            $documentInfo = $this->analyzeDocumentStructure($documentContent);

            return response()->json([
                'success' => true,
                'document_id' => $documentId,
                'info' => $documentInfo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve document info',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate study plan from document
     */
    public function generateStudyPlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'required|string',
            'study_duration' => 'sometimes|integer|min:1|max:168', // hours
            'difficulty_level' => 'sometimes|string|in:beginner,intermediate,advanced',
            'focus_areas' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $documentId = $request->input('document_id');
            $studyDuration = $request->input('study_duration', 10);
            $difficultyLevel = $request->input('difficulty_level', 'intermediate');
            $focusAreas = $request->input('focus_areas', []);

            // Get document content
            $documentContent = $this->getAllDocumentContent($documentId, true);

            if (empty($documentContent)) {
                return response()->json(['error' => 'Document not found'], 404);
            }

            // Generate study plan
            $studyPlan = $this->generateStudyPlanWithAnthropic(
                $documentContent,
                $studyDuration,
                $difficultyLevel,
                $focusAreas
            );

            return response()->json([
                'success' => true,
                'study_plan' => $studyPlan,
                'document_id' => $documentId,
                'parameters' => [
                    'duration' => $studyDuration,
                    'level' => $difficultyLevel,
                    'focus_areas' => $focusAreas
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Study plan generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Private helper methods
    private function storeInVectorDB(string $documentId, array $processedContent, array $baseMetadata): bool
    {
        $documents = [];
        $metadatas = [];
        $ids = [];

        // Track counts for logging
        $logCounts = [
            'combined' => 0,
            'text' => 0,
            'image_description' => 0,
            'transcript' => 0
        ];

        // 1. Store combined content chunks
        if (!empty($processedContent['combined_content'])) {
            foreach ($processedContent['combined_content'] as $index => $content) {
                $documents[] = $content;
                $metadatas[] = array_merge($baseMetadata, [
                    'document_id' => $documentId,
                    'chunk_index' => $index,
                    'content_type' => 'combined'
                ]);
                $ids[] = $documentId . '_chunk_' . $index;
                $logCounts['combined']++;
            }
        }

        // 2. Optional: store raw text chunks separately if needed
        if (!empty($processedContent['text_chunks'])) {
            foreach ($processedContent['text_chunks'] as $index => $chunk) {
                $documents[] = $chunk;
                $metadatas[] = array_merge($baseMetadata, [
                    'document_id' => $documentId,
                    'chunk_index' => $index,
                    'content_type' => 'text'
                ]);
                $ids[] = $documentId . '_text_' . $index;
                $logCounts['text']++;
            }
        }

        // 3. Store image descriptions
        if (!empty($processedContent['image_descriptions'])) {
            foreach ($processedContent['image_descriptions'] as $index => $desc) {
                if (!empty($desc['description'])) {
                    $documents[] = $desc['description'];
                    $metadatas[] = array_merge($baseMetadata, [
                        'document_id' => $documentId,
                        'chunk_index' => $index,
                        'content_type' => 'image_description'
                    ]);
                    $ids[] = $documentId . '_imgdesc_' . $index;
                    $logCounts['image_description']++;
                }
            }
        }

        // 4. Store transcript if available
        if (isset($processedContent['transcript']) || isset($processedContent['audio_transcript'])) {
            $transcript = $processedContent['transcript'] ?? $processedContent['audio_transcript'];
            $documents[] = $transcript;
            $metadatas[] = array_merge($baseMetadata, [
                'document_id' => $documentId,
                'chunk_index' => 0,
                'content_type' => 'transcript'
            ]);
            $ids[] = $documentId . '_transcript';
            $logCounts['transcript']++;
        }

        logger()->info("📥 Preparing to store to vector DB", $logCounts);

        return $this->chromaService->addDocuments($documents, $metadatas, $ids);
    }


    private function getRelevantContent(string $documentId, string $topic, bool $includeVisual): array
    {
        // Query ChromaDB for relevant content
        $results = $this->chromaService->queryDocuments($topic, 10);

        // logger()->info("🔍 Querying ChromaDB for topic '{$topic}'", [
        //     'document_id' => $documentId,
        //     'include_visual' => $includeVisual,
        //     'results_count' => isset($results['metadatas'][0]) ? count($results['metadatas'][0]) : 0,
        //     'results' => $results
        // ]);

        // Filter by document ID and content type
        $relevantContent = [];
        if (isset($results['metadatas'][0])) {
            foreach ($results['metadatas'][0] as $index => $metadata) {
                if (isset($metadata['document_id']) && $metadata['document_id'] === $documentId) {
                    if ($includeVisual || (isset($metadata['content_type']) && $metadata['content_type'] !== 'image_description')) {
                        $relevantContent[] = [
                            'content' => $results['documents'][0][$index],
                            'metadata' => $metadata,
                            'distance' => $results['distances'][0][$index]
                        ];
                    }
                }
            }
        }


        logger()->info("🔍 Relevant content retrieved", [
            'document_id' => $documentId,
            'topic' => $topic,
            'include_visual' => $includeVisual,
            'relevant_content_count' => count($relevantContent),
            'relevant_content' => $relevantContent
        ]);

        return $relevantContent;
    }

    private function getAllDocumentContent(string $documentId, bool $includeMultimedia): array
    {
        // Get all content for a specific document
        // This is a simplified approach - in production, you'd want pagination
        $allResults = $this->chromaService->queryDocuments('', 100);

        $documentContent = [];
        if (isset($allResults['metadatas'][0])) {
            foreach ($allResults['metadatas'][0] as $index => $metadata) {
                if ($metadata['document_id'] === $documentId) {
                    if ($includeMultimedia || $metadata['content_type'] === 'text') {
                        $documentContent[] = [
                            'content' => $allResults['documents'][0][$index],
                            'metadata' => $metadata
                        ];
                    }
                }
            }
        }

        return $documentContent;
    }

    private function generateQuestionsWithContext(array $content, int $count, string $difficulty, array $types): array
    {
        $contextText = '';
        foreach ($content as $item) {
            $contextText .= $item['content'] . "\n\n";
        }

        $typeStr = implode(', ', $types);

        $prompt = "Based on the following study material, generate {$count} questions at {$difficulty} difficulty level. Include these question types: {$typeStr}. 

For multiple choice questions, provide 4 options with one correct answer.
Format the response as a JSON array with objects containing: 'question', 'type', 'options' (for multiple choice), 'correct_answer', 'explanation'.

Study Material:
{$contextText}";

        $response = QuestionGeneratorAgent::make()->chat(
            new UserMessage($prompt)
        );

        $questions = $this->cleanAndDecodeMarkdownJson($response->getContent());

        logger()->info("📝 Generated questions", [
            'count' => count($questions),
            'difficulty' => $difficulty,
            'types' => $types,
            'questions' => $questions
        ]);
        
        return $questions;
    }

    private function generateSummaryWithContext(array $content, string $type, int $maxLength): string
    {
        $contextText = '';
        $hasVisualContent = false;

        foreach ($content as $item) {
            $contextText .= $item['content'] . "\n\n";
            if ($item['metadata']['content_type'] === 'image_description') {
                $hasVisualContent = true;
            }
        }

        $prompts = [
            'brief' => "Provide a brief summary (2-3 paragraphs) of the main points:",
            'detailed' => "Provide a comprehensive summary covering all major topics and concepts:",
            'key_points' => "Extract and organize the key points and important concepts:",
            'visual_summary' => "Create a summary that emphasizes visual elements, charts, diagrams, and multimedia content:"
        ];

        $prompt = $prompts[$type] . "\n\n";

        if ($hasVisualContent) {
            $prompt .= "Note: This content includes visual elements (images, charts, diagrams) that have been described. Pay attention to these visual descriptions when creating the summary.\n\n";
        }

        $prompt .= "Keep the summary under {$maxLength} characters.\n\nContent:\n{$contextText}";

        $result = $this->callAnthropicAPI($prompt, 2000);
        return is_array($result) ? $result[0] ?? 'Failed to generate summary' : $result;
    }

    private function generateStudyPlanWithAnthropic(array $content, int $duration, string $level, array $focusAreas): array
    {
        $contextText = '';
        foreach ($content as $item) {
            $contextText .= $item['content'] . "\n\n";
        }

        $focusStr = empty($focusAreas) ? '' : "Focus especially on: " . implode(', ', $focusAreas) . "\n";

        $prompt = "Based on this study material, create a {$duration}-hour study plan for a {$level} learner.
{$focusStr}
Format as JSON with: 'total_hours', 'sessions' array with 'session_number', 'duration_hours', 'topics', 'activities', 'resources_needed'.

Study Material:
{$contextText}";

        return $this->callAnthropicAPI($prompt, 2000);
    }

    private function callAnthropicAPI(string $prompt, int $maxTokens): mixed
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => config('anthropic.api_key'),
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-3-sonnet-20240229',
                'max_tokens' => $maxTokens,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            if ($response->successful()) {
                $content = $response->json()['content'][0]['text'];

                // Try to parse as JSON first
                $decoded = json_decode($content, true);
                return $decoded !== null ? $decoded : $content;
            }

            return 'API call failed';
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    private function generateContentSummary(array $content): array
    {
        $summary = [];

        if (isset($content['text_chunks'])) {
            $summary['text_chunks'] = count($content['text_chunks']);
        }

        if (isset($content['image_descriptions'])) {
            $summary['images_analyzed'] = count($content['image_descriptions']);
        }

        if (isset($content['transcript'])) {
            $summary['has_transcript'] = true;
        }

        return $summary;
    }

    private function getProcessingStats(array $content): array
    {
        return [
            'total_content_pieces' => count($content['combined_content'] ?? []),
            'content_types' => array_keys($content),
            'processing_time' => now()->toISOString()
        ];
    }

    private function getContentSources(array $content): array
    {
        $sources = [];
        foreach ($content as $item) {
            $sources[] = $item['metadata']['content_type'];
        }
        return array_unique($sources);
    }

    private function getContentStats(array $content): array
    {
        $stats = ['total_chunks' => count($content)];

        $types = array_column(array_column($content, 'metadata'), 'content_type');
        $stats['content_types'] = array_count_values($types);

        return $stats;
    }

    private function getMultimediaElements(array $content): array
    {
        $elements = [];
        foreach ($content as $item) {
            if (in_array($item['metadata']['content_type'], ['image_description', 'transcript'])) {
                $elements[] = $item['metadata']['content_type'];
            }
        }
        return array_unique($elements);
    }

    private function filterSearchResults(array $results, array $documentIds, array $contentTypes): array
    {
        // Implementation for filtering search results
        $filtered = [];

        if (isset($results['metadatas'][0])) {
            foreach ($results['metadatas'][0] as $index => $metadata) {
                $includeDocument = empty($documentIds) || in_array($metadata['document_id'], $documentIds);
                $includeContentType = empty($contentTypes) || in_array($metadata['content_type'], $contentTypes);

                if ($includeDocument && $includeContentType) {
                    $filtered[] = [
                        'content' => $results['documents'][0][$index],
                        'metadata' => $metadata,
                        'distance' => $results['distances'][0][$index]
                    ];
                }
            }
        }

        return $filtered;
    }

    private function analyzeDocumentStructure(array $content): array
    {
        $structure = [
            'total_sections' => count($content),
            'content_breakdown' => [],
            'estimated_reading_time' => 0
        ];

        $types = array_column(array_column($content, 'metadata'), 'content_type');
        $structure['content_breakdown'] = array_count_values($types);

        // Estimate reading time (250 words per minute)
        $totalWords = 0;
        foreach ($content as $item) {
            $totalWords += str_word_count($item['content']);
        }
        $structure['estimated_reading_time'] = ceil($totalWords / 250);

        return $structure;
    }

    private function cleanupTempFiles(): void
    {
        // Clean up temporary files created during processing
        $tempDirs = [
            storage_path('app/temp/pdf_images/'),
            storage_path('app/temp/frames/'),
            storage_path('app/temp/')
        ];

        foreach ($tempDirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*');
                foreach ($files as $file) {
                    if (is_file($file) && (time() - filemtime($file)) > 3600) { // Delete files older than 1 hour
                        unlink($file);
                    }
                }
            }
        }
    }

    public function cleanAndDecodeMarkdownJson(string $markdown): array
    {
        // Remove triple backticks and possible "json" after the opening
        $cleaned = preg_replace('/^```json\s*|\s*```$/', '', trim($markdown));

        // Decode JSON
        $decoded = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            logger()->error("JSON decoding failed", [
                'error' => json_last_error_msg(),
                'raw' => $markdown
            ]);
            return [];
        }

        return $decoded;
    }
}
