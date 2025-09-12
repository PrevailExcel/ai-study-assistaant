<?php

namespace App\Services;

class GttsService
{
    public function synthesize(string $text, string $filename = 'summary.mp3'): string
    {
        $pythonScript = base_path('gtts_service.py');
        $output = shell_exec("python3 {$pythonScript} " . escapeshellarg($text) . " " . escapeshellarg($filename));

        if (!$output) {
            throw new \Exception("Python script failed or returned no output.");
        }

        $path = trim($output);

        if (!file_exists($path)) {
            throw new \Exception("TTS file was not created: {$path}");
        }

        return asset("storage/{$filename}");
    }
}
