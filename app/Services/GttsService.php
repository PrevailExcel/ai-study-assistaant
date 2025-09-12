<?php

namespace App\Services;

class GttsService
{
    public function synthesize(string $text, string $filename = 'summary.mp3'): string
    {
        $pythonScript = base_path('gtts_service.py');
        $output = shell_exec("python3 {$pythonScript} " . escapeshellarg($text) . " " . escapeshellarg($filename));

        $path = trim($output);
        return asset("storage/{$filename}");
    }
}
