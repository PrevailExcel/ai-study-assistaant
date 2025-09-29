<?php

use Illuminate\Support\Facades\Route;
use App\Services\PaystackService;


Route::get('/', function () {
    // Example usage of the GeminiAgent
    // $talkToGemini = new \App\Services\TalkToGemini();
    // $response = $talkToGemini->talk();
    // dd($response);
    return view('welcome');
});

Route::get('/plans/basic', function (PaystackService $paystack) {
    $data = [
        'name' => 'Basic Plan',
        'amount' => 200000, // ₦2000 in kobo
        'interval' => 'monthly',
        'currency' => 'NGN',
    ];

    return response()->json($paystack->createPlan($data));
});

Route::get('/plans/premium', function (PaystackService $paystack) {
    $data = [
        'name' => 'Premium Plan',
        'amount' => 500000, // ₦5,000 in kobo
        'interval' => 'monthly',
        'currency' => 'NGN',
    ];

    return response()->json($paystack->createPlan($data));
});
