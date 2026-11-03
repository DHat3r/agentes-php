<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Pagination\LengthAwarePaginator;

class QuestionController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        return Question::query()
            ->select(['id', 'question_text', 'human_answer', 'category'])
            ->orderBy('id')
            ->paginate(10);
    }
}
