<?php

namespace App\Services;

use App\Agents\GeminiAgent;
use NeuronAI\Chat\Messages\UserMessage;

class TalkToGemini
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function talk()
    {
        $response = GeminiAgent::make()
            ->chat(
                new UserMessage("What is my bride price? I am from Igbo culture, 30 years old, a graduate, and a civil servant. My father is a retired teacher and my mother is a trader. I am the first child in my family.")
            );

        return $response->getContent();
        
    }
}
