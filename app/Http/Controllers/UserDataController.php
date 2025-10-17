<?php

namespace App\Http\Controllers;

use App\Helpers\PaginationHelper;
use App\Models\Document;
use App\Models\Flashcard;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Summary;
use App\Services\TtsFactory;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserDataController extends Controller
{
    public function listDocuments(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->input('per_page', 10);
            $perPage = $perPage > 100 ? 100 : $perPage;

            $query = Document::where('user_id', $request->user()->id);

            // 🔍 Optional: Search by title or metadata
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('metadata->original_name', 'like', "%{$search}%")
                        ->orWhere('metadata->mime_type', 'like', "%{$search}%");
                });
            }

            // 🎯 Filter by study method (summary, flashcard, mcq)
            if ($filter = $request->input('filter')) {
                $filter = strtolower($filter);

                $query->where(function ($q) use ($filter) {
                    if (str_contains($filter, 'summary')) {
                        $q->orWhereHas('summaries');
                    }
                    if (str_contains($filter, 'flashcard')) {
                        $q->orWhereHas('flashcards');
                    }
                    if (str_contains($filter, 'mcq')) {
                        $q->orWhereHas('questions');
                    }
                });
            }

            $documents = $query
                ->withCount(['summaries', 'flashcards', 'questions']) // Optional for metadata
                ->paginate($perPage);

            return $this->success([
                'documents' => $documents->items(),
                'pagination' => PaginationHelper::format($documents)
            ]);
        } catch (\Exception $e) {
            return $this->error(
                'Failed to retrieve documents. ' . $e->getMessage(),
                500
            );
        }
    }

    public function getDocumentDetails(Request $request, $documentId)
    {
        $document = Document::with('topics')->where('doc_id', $documentId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$document) {
            return $this->error('Document not found', 404);
        }

        return $this->success([
            'document' => $document
        ]);
    }

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

        $summaries = Summary::where('user_id', $request->user()->id)
            ->where('document_id', $request->document_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('batch_id')
            ->values(); // reindex groups numerically

        // Paginate the grouped batches manually (since paginate() won't work with grouped data)
        $page = (int) $request->input('page', 1);
        $total = $summaries->count();
        $paged = $summaries->forPage($page, $perPage)->values();

        return $this->success([
            'summaries' => $paged,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]
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

    public function submitAnswers(Request $request)
    {
        $request->validate([
            'quiz_id' => 'required|string',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.user_answer' => 'sometimes|string|nullable',
        ]);

        $quiz = Quiz::where('id', $request->quiz_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$quiz) {
            return $this->error('Quiz not found', 404);
        }

        $answers = collect($request->answers);

        $correctScores = 0;

        foreach ($answers as $answer) {
            $question = Question::find($answer['question_id']);
            if (!$question || $question->quiz->user_id != $request->user()->id) {
                continue;
            }

            $isCorrect = strtolower(trim($question->correct_answer)) === strtolower(trim($answer['user_answer']));

            $question->is_correct = $isCorrect;
            $question->user_answer = $answer['user_answer'];
            $question->save();

            if ($isCorrect) {
                $correctScores++;
            }
        }

        $totalQuestions = $answers->count();
        $score = "$correctScores/$totalQuestions";

        $all = Question::where('quiz_id', $request->quiz_id)->get();

        return $this->success([
            'score' => $score,
            'questions' => $all
        ]);
    }

    public function summaryToAudio(Request $request)
    {
        try {
            $summary = Summary::find($request->summary_id);
            if (!$summary) {
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
