<?php

namespace App\Agents;

use NeuronAI\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\Gemini\Gemini;

class ImageAnalyzerAgent extends Agent
{
    protected function provider(): AIProviderInterface
    {
        return new Gemini(
            key: env('GEMINI_API_KEY'),
            model: 'gemini-2.0-flash',
        );
    }

    public function instructions(): string
    {
        return "You are an educational assistant. Analyze the provided image (charts, diagrams, formulas, textual content) in detail.";
    }

    public function analyzeImage(string $imagePath): string
    {
        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType  = mime_content_type($imagePath);

        $response = $this
            ->chat(new UserMessage([
                [
                    'type' => 'text',
                    'text' => 'Analyze this image. Focus on charts, diagrams, equations, and educational content in detail.'
                ],
                [
                    'type' => 'image',
                    'source' => [
                        'type'       => 'base64',
                        'media_type' => $mimeType,
                        'data'       => $imageData
                    ]
                ]
            ]));

        return $response->getContent();
    }
}
