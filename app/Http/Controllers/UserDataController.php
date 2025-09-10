<?php

namespace App\Http\Controllers;

use App\Helpers\PaginationHelper;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Summary;
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
            ->paginate($perPage);

        return $this->success([
            'summaries' => $summaries->items(),
            'pagination' => PaginationHelper::format($summaries)
        ]);
    }
}
