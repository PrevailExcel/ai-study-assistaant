<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Example usage of the GeminiAgent
    $talkToGemini = new \App\Services\TalkToGemini();
    $response = $talkToGemini->talk();
    dd($response);
});
