<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpPresentation\IOFactory as PresentationIOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

class MultimediaFileProcessor
{
    private ChromaService $chromaService;
    
    public function __construct(ChromaService $chromaService)
    {
        $this->chromaService = $chromaService;
    }

    /**
     * Process different file types
     */
    public function processFile(string $filePath, string $fileType): array
    {
        switch ($fileType) {
            case 'pdf':
                return (new MultimediaPdfProcessor($this->chromaService))->processPdf($filePath);
            case 'pptx':
            case 'ppt':
                return $this->processPowerPoint($filePath);
            case 'docx':
            case 'doc':
                return $this->processWord($filePath);
            case 'mp4':
            case 'mov':
            case 'avi':
                return $this->processVideo($filePath);
            case 'mp3':
            case 'wav':
                return $this->processAudio($filePath);
            default:
                throw new \Exception("Unsupported file type: {$fileType}");
        }
    }

    /**
     * Process PowerPoint presentations
     */
    private function processPowerPoint(string $filePath): array
    {
        $results = [
            'text_content' => [],
            'slide_descriptions' => [],
            'combined_content' => []
        ];

        try {
            // Read presentation
            $presentation = PresentationIOFactory::load($filePath);
            
            foreach ($presentation->getAllSlides() as $slideIndex => $slide) {
                $slideText = '';
                $slideImages = [];
                
                // Extract text from shapes
                foreach ($slide->getShapeCollection() as $shape) {
                    if ($shape instanceof \PhpOffice\PhpPresentation\Shape\RichText) {
                        foreach ($shape->getParagraphs() as $paragraph) {
                            foreach ($paragraph->getRichTextElements() as $element) {
                                $slideText .= $element->getText() . ' ';
                            }
                        }
                    }
                    
                    // Handle images and charts
                    if ($shape instanceof \PhpOffice\PhpPresentation\Shape\Drawing) {
                        $slideImages[] = $shape->getPath();
                    }
                }
                
                $results['text_content'][] = [
                    'slide' => $slideIndex + 1,
                    'text' => trim($slideText)
                ];

                // Analyze slide images if any
                foreach ($slideImages as $imagePath) {
                    $description = $this->analyzeImageWithClaude($imagePath);
                    $results['slide_descriptions'][] = [
                        'slide' => $slideIndex + 1,
                        'image_description' => $description
                    ];
                }
            }

            // Create combined content
            $results['combined_content'] = $this->combineSlideContent($results);
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to process PowerPoint: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Process Word documents
     */
    private function processWord(string $filePath): array
    {
        $results = [
            'text_content' => '',
            'image_descriptions' => [],
            'combined_content' => []
        ];

        try {
            $phpWord = WordIOFactory::load($filePath);
            $text = '';
            
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                        foreach ($element->getElements() as $textElement) {
                            if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                $text .= $textElement->getText() . ' ';
                            }
                        }
                    }
                    
                    // Handle images
                    if ($element instanceof \PhpOffice\PhpWord\Element\Image) {
                        $description = $this->analyzeImageWithClaude($element->getSource());
                        $results['image_descriptions'][] = $description;
                    }
                }
            }
            
            $results['text_content'] = $text;
            $results['combined_content'] = $this->combineWordContent($results);
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to process Word document: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Process video files (extract frames and audio)
     */
    private function processVideo(string $filePath): array
    {
        $results = [
            'audio_transcript' => '',
            'frame_descriptions' => [],
            'combined_content' => []
        ];

        try {
            // Extract audio and transcribe
            $audioPath = $this->extractAudioFromVideo($filePath);
            $results['audio_transcript'] = $this->transcribeAudio($audioPath);
            
            // Extract key frames
            $frames = $this->extractVideoFrames($filePath);
            foreach ($frames as $index => $framePath) {
                $description = $this->analyzeImageWithClaude($framePath);
                $results['frame_descriptions'][] = [
                    'timestamp' => $index * 30, // Every 30 seconds
                    'description' => $description
                ];
            }
            
            $results['combined_content'] = $this->combineVideoContent($results);
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to process video: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Process audio files
     */
    private function processAudio(string $filePath): array
    {
        return [
            'transcript' => $this->transcribeAudio($filePath),
            'combined_content' => [$this->transcribeAudio($filePath)]
        ];
    }

    private function extractAudioFromVideo(string $videoPath): string
    {
        $audioPath = storage_path('app/temp/audio_' . uniqid() . '.wav');
        $command = "ffmpeg -i '{$videoPath}' -acodec pcm_s16le -ac 1 -ar 16000 '{$audioPath}'";
        exec($command);
        return $audioPath;
    }

    private function extractVideoFrames(string $videoPath, int $intervalSeconds = 30): array
    {
        $framesDir = storage_path('app/temp/frames/');
        if (!file_exists($framesDir)) {
            mkdir($framesDir, 0755, true);
        }
        
        $command = "ffmpeg -i '{$videoPath}' -vf fps=1/{$intervalSeconds} '{$framesDir}frame_%03d.jpg'";
        exec($command);
        
        return glob($framesDir . '*.jpg');
    }

    private function transcribeAudio(string $audioPath): string
    {
        try {
            // Using OpenAI Whisper API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('openai.api_key'),
            ])->attach(
                'file', file_get_contents($audioPath), basename($audioPath)
            )->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1'
            ]);

            if ($response->successful()) {
                return $response->json()['text'];
            }
        } catch (\Exception $e) {
            // Fallback or error handling
        }

        return 'Failed to transcribe audio';
    }

    private function analyzeImageWithClaude(string $imagePath): string
    {
        // Same implementation as in the previous artifact
        try {
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
                                'text' => 'Analyze this educational content. Describe charts, diagrams, equations, key concepts, or any important visual information for studying.'
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
            return 'Failed to analyze image';
        }

        return 'Unable to analyze image';
    }

    private function combineSlideContent(array $results): array
    {
        $combined = [];
        
        foreach ($results['text_content'] as $slide) {
            $content = "Slide {$slide['slide']}: {$slide['text']}";
            
            // Add image descriptions for this slide
            foreach ($results['slide_descriptions'] as $desc) {
                if ($desc['slide'] === $slide['slide']) {
                    $content .= "\n\n[VISUAL CONTENT]: " . $desc['image_description'];
                }
            }
            
            $combined[] = $content;
        }
        
        return $combined;
    }

    private function combineWordContent(array $results): array
    {
        $combined = $results['text_content'];
        
        foreach ($results['image_descriptions'] as $desc) {
            $combined .= "\n\n[IMAGE DESCRIPTION]: " . $desc;
        }
        
        return [$combined];
    }

    private function combineVideoContent(array $results): array
    {
        $combined = "TRANSCRIPT: " . $results['audio_transcript'];
        
        foreach ($results['frame_descriptions'] as $frame) {
            $combined .= "\n\n[VISUAL AT {$frame['timestamp']}s]: " . $frame['description'];
        }
        
        return [$combined];
    }
}