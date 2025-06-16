<?php

use App\Http\Controllers\EnhancedStudyAssistantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('study')->group(function () {
    
    // File upload and processing
    Route::post('/upload', [EnhancedStudyAssistantController::class, 'uploadFile'])
        ->name('study.upload');
    
    // Question generation
    Route::post('/generate-questions', [EnhancedStudyAssistantController::class, 'generateQuestions'])
        ->name('study.generate-questions');
    
    // Summary generation
    Route::post('/generate-summary', [EnhancedStudyAssistantController::class, 'generateSummary'])
        ->name('study.generate-summary');
    
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