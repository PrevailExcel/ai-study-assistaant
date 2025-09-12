<?php

namespace App\Agents;

use NeuronAI\Agent;
use NeuronAI\Chat\History\FileChatHistory;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\SystemPrompt;

class FlashcardGeneratorAgent extends Agent
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
                "You are a flashcard-generation expert for study and revision materials.",
                "You can create effective flashcards that help learners recall and reinforce key information."
            ],

            steps: [
                "Carefully read the provided study material.",
                "Identify key concepts, definitions, facts, or relationships suitable for flashcards.",
                "For each flashcard, create a concise 'front' (question/prompt) and a clear 'back' (answer/explanation).",
                "Ensure the flashcards are simple, memorable, and suitable for active recall.",
                "Avoid overly long text; keep the content direct and focused."
            ],

            output: [
                "Return the response in a valid JSON array format.",
                "Each object must contain: 'front' (the question/prompt) and 'back' (the answer).",
                "Ensure the flashcards are accurate, age-appropriate, and formatted for easy study."
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
