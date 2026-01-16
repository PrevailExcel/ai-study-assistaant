<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EnhancedStudyAssistantController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserDataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('v1')->group(function () {
// Add this temporary debug endpoint or artisan command
Route::get('/debug/qdrant/{documentId}', function($documentId) {
    $qdrant = app(QuadrantService::class);
    
    // Try to get points by document_id
    $results = $qdrant->search(
        array_fill(0, 1536, 0.1), // Dummy vector
        100,
        [
            'must' => [
                ['key' => 'document_id', 'match' => ['value' => $documentId]]
            ]
        ],
        0.0
    );
    
    return response()->json([
        'document_id' => $documentId,
        'points_found' => count($results),
        'sample_points' => array_slice($results, 0, 3)
    ]);
});

    Route::post('/register', [AuthController::class, 'register'])
        ->name('auth.register');
    Route::post('/login-third-party', [AuthController::class, 'registerWithThirdParty'])
        ->name('auth.register.third-party');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'sendCode']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
    Route::get('/plans', [SubscriptionController::class, 'listPlans']);
    Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);


    Route::middleware('auth:sanctum')->prefix('study')->group(function () {

        // Subscription management
        Route::prefix('subscription')->group(function () {
            Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);
            Route::post('/verify', [SubscriptionController::class, 'verify']);
            Route::get('/current', [SubscriptionController::class, 'current']);
            Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        });


        // File upload and processing
        Route::post('/upload', [EnhancedStudyAssistantController::class, 'uploadFile'])
            ->name('study.upload');

        // List documents
        Route::get('/documents', [UserDataController::class, 'listDocuments']);

        // Get document
        Route::get('/document/{documentId}', [UserDataController::class, 'getDocumentDetails']);

        //Name document
        Route::post('/document/{documentId}/name', [EnhancedStudyAssistantController::class, 'nameDocument'])
            ->name('study.name-document');

        // Question generation
        Route::post('/generate-questions', [EnhancedStudyAssistantController::class, 'generateQuestions'])
            ->name('study.generate-questions');

        // Summary generation
        Route::post('/generate-summary', [EnhancedStudyAssistantController::class, 'generateSummary'])
            ->name('study.generate-summary');

        // Flashcard generation
        Route::post('/generate-flashcard', [EnhancedStudyAssistantController::class, 'generateFlashcards'])
            ->name('study.generate-flashcard');

        // Topic Extraction
        Route::post('/extract-topics', [EnhancedStudyAssistantController::class, 'extractTopics'])
            ->name('study.extract-topics');

        // List Summaries by Document
        Route::get('/summaries', [UserDataController::class, 'listSummaries']);

        // Submit answers
        Route::post('/submit-answers', [UserDataController::class, 'submitAnswers']);

        // Convert summary to audio
        Route::get('/summaries/convert-to-audio', [UserDataController::class, 'summaryToAudio']);

        // List Flashcards by Document
        Route::get('/flashcards', [UserDataController::class, 'listFlashcards']);

        // List Quizes by Document
        Route::get('/documents/quizes', [UserDataController::class, 'listQuizesByDocuments']);

        // List Questions by Quizes
        Route::get('/documents/quizes/questions', [UserDataController::class, 'listQuestionsByQuiz']);

        // Content search
        Route::post('/search', [EnhancedStudyAssistantController::class, 'searchContent'])
            ->name('study.search');

        // Study plan generation
        Route::post('/generate-study-plan', [EnhancedStudyAssistantController::class, 'generateStudyPlan'])
            ->name('study.generate-study-plan');


        Route::middleware(['subscription:basic,premium'])->group(function () {
            Route::get('/premium-feature', function () {
                return response()->json(['message' => 'Premium content']);
            });
        });

        Route::middleware(['subscription:premium'])->group(function () {
            Route::get('/premium-only', function () {
                return response()->json(['message' => 'Premium only content']);
            });
        });
    });
});

Route::get('login', function (Request $request) {
    return response()->json(['success' => false, 'message' => 'Login to access our data'], 401);
})->name('login');

Route::post('/webhook/paystack', [PaystackWebhookController::class, 'handle'])
    ->name('paystack.webhook');
Route::get('/callback/paystack', [SubscriptionController::class, 'callback'])
    ->name('paystack.callback');
