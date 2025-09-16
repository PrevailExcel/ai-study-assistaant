<?php

namespace App\Agents;

use NeuronAI\Agent;
use NeuronAI\Chat\History\FileChatHistory;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\SystemPrompt;

class SummaryGeneratorAgent extends Agent
{
    public function provider(): AIProviderInterface
    {
        return new Gemini(
            key: env('GEMINI_API_KEY'),
            model: 'gemini-2.0-flash',
        );
    }

    public function instructions(): string
    {
        return new SystemPrompt(
            background: [
                "You are an expert at summarizing study materials.",
                "You can generate summaries in different styles: brief, detailed, key points, or visual-focused."
            ],

            steps: [
                "Read the provided study material carefully.",
                "Identify the central themes, important details, and supporting concepts.",
                "Adjust the summary style based on the requested type (brief, detailed, key points, visual).",
                "Ensure clarity, conciseness, and logical flow."
            ],

            output: [
                "Return only plain text, without markdown or formatting.",
                "Ensure the summary is under the specified maximum length.",
                "If visual content is included, highlight and integrate its significance."
            ],
        );

    }

    protected function chatHistory(): FileChatHistory
    {
        return new FileChatHistory(
            directory: storage_path('app/public/'),
            key: '[user-id].summary-generator',
            contextWindow: 50000
        );
    }
}
