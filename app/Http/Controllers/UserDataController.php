<?php

namespace App\Http\Controllers;

use App\Helpers\PaginationHelper;
use App\Models\Flashcard;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Summary;
use App\Services\TtsFactory;
use Exception;
use Illuminate\Http\Request;

class UserDataController extends Controller
{
    public function listQuizesByDocuments(Request $request)
    {

        $perPage = (int) $request->input('per_page', 10);
        $perPage = $perPage > 100 ? 100 : $perPage;

        $quizes = Quiz::where('user_id', request()->user()->id)
            ->where('document_id', $request->document_id)
            ->paginate($perPage);

        return $this->success([
            'quizes' => $quizes->items(),
            'pagination' => PaginationHelper::format($quizes)
        ]);
    }

    public function listQuestionsByQuiz(Request $request)
    {

        $perPage = (int) $request->input('per_page', 10);
        $perPage = $perPage > 100 ? 100 : $perPage;

        $quizes = Question::where('quiz_id', $request->quiz_id)
            ->paginate($perPage);

        return $this->success([
            'questions' => $quizes->items(),
            'pagination' => PaginationHelper::format($quizes)
        ]);
    }

    public function listSummaries(Request $request)
    {

        $perPage = (int) $request->input('per_page', 10);
        $perPage = $perPage > 100 ? 100 : $perPage;

        $summaries = Summary::where('user_id', request()->user()->id)
            ->where('document_id', $request->document_id)
            ->paginate($perPage);

        return $this->success([
            'summaries' => $summaries->items(),
            'pagination' => PaginationHelper::format($summaries)
        ]);
    }

    public function listFlashcards(Request $request)
    {

        $perPage = (int) $request->input('per_page', 10);
        $perPage = $perPage > 100 ? 100 : $perPage;

        $summaries = Flashcard::where('user_id', request()->user()->id)
            ->where('document_id', $request->document_id)
            ->paginate($perPage);

        return $this->success([
            'flashcards' => $summaries->items(),
            'pagination' => PaginationHelper::format($summaries)
        ]);
    }

    public function answerQuestion(Request $request)
    {
        $request->validate([
            'question_id' => 'required|string',
            'answer' => 'required|string',
        ]);

        $question = Question::where('id', $request->question_id)
            ->where('user_id', request()->user()->id)
            ->first();

        if (!$question) {
            return $this->error('Question not found', 404);
        }

        $isCorrect = strtolower(trim($question->answer)) === strtolower(trim($request->answer));

        return $this->success([
            'correct' => $isCorrect,
            'correct_answer' => $question->answer
        ]);
    }

    public function summaryToAudio(Request $request)
    {
        try {
            $summary = Summary::find($request->summary_id);
            if(!$summary){
                return $this->error('Summary was not found or does not belong to this user.');
            }

            $text = $summary->content;

            $tts = TtsFactory::make();
            $audioUrl = $tts->synthesize($text, 'summary.mp3');

            return $this->success([
                'audio_url' => $audioUrl
            ]);
        } catch (Exception $e) {
            return $this->error('Error in processing audio: ' . $e->getMessage());
        }
    }
}
