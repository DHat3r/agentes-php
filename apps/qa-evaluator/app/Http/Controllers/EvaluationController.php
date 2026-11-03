<?php

namespace App\Http\Controllers;

use App\Http\Resources\EvaluationResource;
use App\Jobs\EvaluateQuestion;
use App\Models\Evaluation;
use App\Support\EvaluationCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EvaluationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return EvaluationResource::collection(Evaluation::query()->latest('id')->paginate(20));
    }

    /**
     * Si la pregunta ya tiene una evaluación en caché la devuelve (200);
     * si no, encola una nueva y responde 202 Accepted: el trabajo sigue en segundo plano.
     */
    public function store(Request $request, EvaluationCache $cache): JsonResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'force' => ['sometimes', 'boolean'],
        ]);

        if (! ($data['force'] ?? false) && ($cached = $cache->lookup($data['question_id'])) !== null) {
            return response()->json([...(new EvaluationResource($cached))->resolve(), 'cached' => true]);
        }

        $evaluation = Evaluation::create(['question_id' => $data['question_id']]);
        EvaluateQuestion::dispatch($evaluation);

        return response()->json([...(new EvaluationResource($evaluation->refresh()))->resolve(), 'cached' => false], 202);
    }

    public function show(Evaluation $evaluation): EvaluationResource
    {
        return new EvaluationResource($evaluation);
    }
}
