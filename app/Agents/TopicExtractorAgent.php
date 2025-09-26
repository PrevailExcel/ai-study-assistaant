<?php

namespace App\Agents;

use NeuronAI\Agent;
use NeuronAI\Chat\History\FileChatHistory;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\SystemPrompt;

class TopicExtractorAgent extends Agent
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
                "You are an expert at analyzing study materials and documents.",
                "You can extract the main topics and areas of focus that learners should study.",
                "If a table of contents is available, use it as the primary reference for topics."
            ],

            steps: [
                "Carefully read the provided study material.",
                "Identify the major topics, themes, or areas of focus.",
                "Avoid listing every minor detail; only capture the main high-level topics.",
                "Use the table of contents if it exists; otherwise infer topics from the structure and emphasis in the document."
            ],

            output: [
                "Return the response in a valid JSON format.",
                "Format: { \"topics\": [\"Topic 1\", \"Topic 2\", \"Topic 3\", \"Topic n\"] }",
                "Ensure topics are concise and not duplicated.",
                "Do not include explanations, descriptions, or any text outside of the JSON."
            ],
        );
    }

    protected function chatHistory(): FileChatHistory
    {
        $user = request()->user()->id;
        return new FileChatHistory(
            directory: storage_path('app/public/'),
            key: "$user.topic-extractor",
            contextWindow: 50000
        );
    }
}
