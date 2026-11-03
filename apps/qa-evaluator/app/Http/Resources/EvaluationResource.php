<?php

namespace App\Http\Resources;

use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Forma pública de una evaluación: separa el JSON de la API de las columnas de la tabla.
 *
 * @mixin Evaluation
 */
class EvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_id' => $this->question_id,
            'status' => $this->status,
            'llm_answer' => $this->llm_answer,
            'similarity_score' => $this->similarity_score,
            'quality_score' => $this->quality_score,
            'completeness_score' => $this->completeness_score,
            'overall_score' => $this->overall_score,
            'attempts' => $this->attempts,
            'trace' => $this->trace ?? [],
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
