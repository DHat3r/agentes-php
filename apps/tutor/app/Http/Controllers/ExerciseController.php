<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Student;
use App\Tutor\Topics;
use App\Tutor\TutorAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * El agente corre dentro de la petición (puede tardar ~20 s). En el tutor es aceptable porque
 * el estudiante espera el ejercicio; en el evaluador QA, con lotes grandes, se usa una cola.
 */
class ExerciseController extends Controller
{
    public function store(Request $request, Student $student, TutorAgent $tutor): JsonResponse
    {
        $data = $request->validate(['topic' => ['required', Rule::in(Topics::names())]]);

        [$exercise, $result] = $tutor->generate($student, $data['topic']);

        return response()->json([
            'id' => $exercise->id,
            'topic' => $exercise->topic,
            'level' => $exercise->level,
            'statement' => $exercise->statement,
            'trace' => $result->trace(),
        ], 201);
    }

    public function answer(Request $request, Exercise $exercise, TutorAgent $tutor): JsonResponse
    {
        $data = $request->validate(['answer' => ['required', 'string', 'max:500']]);

        if ($exercise->isAnswered()) {
            return response()->json(['message' => 'Este ejercicio ya fue respondido.'], 409);
        }

        [$exercise, $result, $newLevel] = $tutor->grade($exercise, $data['answer']);

        return response()->json([
            'id' => $exercise->id,
            'correct' => $exercise->correct,
            'feedback' => $exercise->feedback,
            'new_level' => $newLevel,
            'trace' => $result->trace(),
        ]);
    }
}
