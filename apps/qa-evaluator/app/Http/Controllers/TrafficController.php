<?php

namespace App\Http\Controllers;

use App\Jobs\EvaluateQuestion;
use App\Models\Evaluation;
use App\Models\Question;
use App\Support\TrafficSampler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Simula tráfico: elige preguntas según una distribución y encola una evaluación por cada una.
 */
class TrafficController extends Controller
{
    public function store(Request $request, TrafficSampler $sampler): JsonResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:50'],
            'distribution' => ['required', Rule::in(TrafficSampler::DISTRIBUTIONS)],
        ]);

        $ids = $sampler->sample(Question::query()->orderBy('id')->pluck('id')->all(), $data['count'], $data['distribution']);

        foreach ($ids as $id) {
            EvaluateQuestion::dispatch(Evaluation::create(['question_id' => $id]));
        }

        return response()->json(['queued' => count($ids), 'question_ids' => $ids], 202);
    }
}
