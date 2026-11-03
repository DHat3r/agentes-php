<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Tutor\Topics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Student::query()->orderBy('name')->get(['id', 'name']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        return response()->json(Student::create($data)->only(['id', 'name']), 201);
    }

    /** Progreso en todos los temas, incluso los que aún no practicó (nivel 1). */
    public function progress(Student $student): JsonResponse
    {
        $existing = $student->progress()->get()->keyBy('topic');

        return response()->json(array_map(fn (string $topic) => [
            'topic' => $topic,
            'level' => $existing[$topic]->level ?? 1,
            'correct_streak' => $existing[$topic]->correct_streak ?? 0,
            'wrong_streak' => $existing[$topic]->wrong_streak ?? 0,
        ], Topics::names()));
    }

    public function topics(): JsonResponse
    {
        return response()->json(Topics::names());
    }
}
