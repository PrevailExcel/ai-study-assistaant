<?php

namespace App\Services;

use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\Client\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\SynthesizeSpeechRequest;

class TextToSpeechService
{
    public function synthesize(string $text, string $filename = 'summary.mp3'): string
    {

        $client = new TextToSpeechClient();

        $input = new SynthesisInput();
        $input->setText($text);

        $voice = new VoiceSelectionParams();
        $voice->setLanguageCode('en-US');
        $voice->setName('en-US-Wavenet-D'); // pick one of Google’s voices

        $audioConfig = new AudioConfig();
        $audioConfig->setAudioEncoding(AudioEncoding::MP3);

        // ✅ Build the request properly
        $request = new SynthesizeSpeechRequest();
        $request->setInput($input);
        $request->setVoice($voice);
        $request->setAudioConfig($audioConfig);

        // Call API
        $response = $client->synthesizeSpeech($request);

        // Save MP3 file
        file_put_contents(storage_path('app/public/summary.mp3'), $response->getAudioContent());

        $client->close();

        return asset("storage/{$filename}");
    }
}
