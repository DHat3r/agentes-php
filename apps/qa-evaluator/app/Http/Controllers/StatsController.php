<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Question;
use App\Support\EvaluationCache;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __invoke(EvaluationCache $cache): JsonResponse
    {
        $byStatus = Evaluation::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $average = Evaluation::query()->where('status', Evaluation::COMPLETED)->avg('overall_score');

        return response()->json([
            'questions' => Question::count(),
            'evaluations' => [
                'completed' => (int) ($byStatus[Evaluation::COMPLETED] ?? 0),
                'failed' => (int) ($byStatus[Evaluation::FAILED] ?? 0),
                'pending' => (int) (($byStatus[Evaluation::QUEUED] ?? 0) + ($byStatus[Evaluation::RUNNING] ?? 0)),
            ],
            'average_overall' => $average === null ? null : round((float) $average, 4),
            'cache' => $cache->stats(),
        ]);
    }
}
