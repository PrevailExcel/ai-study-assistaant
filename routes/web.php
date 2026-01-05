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
Route::get('/capture', function () {
    return view('success');
})->name('success');

Route::get('/plans/basic', function (PaystackService $paystack) {
    $data = [
        'name' => 'Basic Plan',
        'amount' => 200000, // ₦2000 in kobo
        'interval' => 'monthly',
        'currency' => 'NGN',
    ];

    return response()->json($paystack->createPlan($data));
});

Route::get('send-sample-email', function () {
    // Logic to send a sample email
    try {
        \Mail::raw('This is a sample email sent from the Laravel application.', function ($message) {
            $message->to('prevailejimadu@gmail.com')
                ->subject('Sample Email');
        });
        return 'Sample email sent successfully!';
    } catch (Exception $e) {
        return 'Failed to send email: ' . $e->getMessage();
    }
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
