<?php

namespace App\Models;

use App\Agent\JudgeScores;
use Database\Factories\EvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    /** @use HasFactory<EvaluationFactory> */
    use HasFactory;

    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $fillable = [
        'question_id', 'status', 'llm_answer', 'similarity_score', 'quality_score',
        'completeness_score', 'overall_score', 'attempts', 'trace', 'error', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'trace' => 'array',
            'similarity_score' => 'float',
            'quality_score' => 'float',
            'completeness_score' => 'float',
            'overall_score' => 'float',
            'completed_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function complete(string $answer, JudgeScores $scores): void
    {
        $this->update([
            'status' => self::COMPLETED,
            'llm_answer' => $answer,
            ...$scores->toArray(),
            'error' => null,
            'completed_at' => now(),
        ]);
    }

    public function fail(string $error, ?array $trace = null): void
    {
        $this->update(['status' => self::FAILED, 'error' => $error, 'trace' => $trace ?? $this->trace]);
    }
}
