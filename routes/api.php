<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EnhancedStudyAssistantController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserDataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('v1')->group(function () {

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

        // File upload and processing
        Route::post('/upload', [EnhancedStudyAssistantController::class, 'uploadFile'])
            ->name('study.upload');

        // List documents
        Route::get('/documents', [EnhancedStudyAssistantController::class, 'listDocuments']);

        //Name document
        Route::post('/document/{documentId}/name', [EnhancedStudyAssistantController::class, 'nameDocument'])
            ->name('study.name-document');

        // Question generation
        Route::post('/generate-questions', [EnhancedStudyAssistantController::class, 'generateQuestions'])
            ->name('study.generate-questions');

        // Summary generation
        Route::post('/generate-summary', [EnhancedStudyAssistantController::class, 'generateSummary'])
            ->name('study.generate-summary');

        // List Summaries
        Route::get('/summaries', [UserDataController::class, 'listSummaries']);

        // List Quizes by Document
        Route::get('/documents/quizes', [UserDataController::class, 'listQuizesByDocuments']);

        // List Questions by Quizes
        Route::get('/documents/quizes/questions', [UserDataController::class, 'listQuestionsByQuiz']);

        // Content search
        Route::post('/search', [EnhancedStudyAssistantController::class, 'searchContent'])
            ->name('study.search');

        // Document information
        Route::get('/document/{documentId}', [EnhancedStudyAssistantController::class, 'getDocumentInfo'])
            ->name('study.document-info');

        // Study plan generation
        Route::post('/generate-study-plan', [EnhancedStudyAssistantController::class, 'generateStudyPlan'])
            ->name('study.generate-study-plan');
    });
});

Route::get('login', function (Request $request) {
    return response()->json(['success' => false, 'message' => 'Login to access our data'], 401);
})->name('login');
