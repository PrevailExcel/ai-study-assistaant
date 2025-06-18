<?php

namespace App\Agents;

use NeuronAI\Agent;
use NeuronAI\Chat\History\FileChatHistory;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\SystemPrompt;

class QuestionGeneratorAgent extends Agent
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
                "You are a question-generation expert for study and assessment materials.",
                "You can generate multiple types of questions from content, including multiple-choice, true/false, and open-ended questions."
            ],

            steps: [
                "Carefully read the provided study material.",
                "Identify the key points and concepts that learners should understand.",
                "Generate clear, educational questions based on the specified number, difficulty level, and question types.",
                "For multiple choice questions, generate 4 options and indicate the correct one.",
                "Include short explanations for each correct answer to help with learning."
            ],

            output: [
                "Return the response in a valid JSON array format.",
                "Each object must contain: 'question', 'type', 'options' (if applicable), 'correct_answer', 'explanation'.",
                "Ensure the content is accurate and age-appropriate for learning."
            ],
        );
    }

    protected function chatHistory(): FileChatHistory
    {
        return new FileChatHistory(
            directory: storage_path('app/public/'),
            key: '[user-id].question-generator',
            contextWindow: 50000
        );
    }
}
