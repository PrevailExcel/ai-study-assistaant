<?php

namespace App\Services;

class TtsFactory
{
    public static function make()
    {
        $driver = config('tts.driver');

        return match ($driver) {
            'google' => new TextToSpeechService(), // Google Cloud TTS
            'gtts'   => new GttsService(),         // Python gTTS
            default  => new GttsService(),
        };
    }
}
