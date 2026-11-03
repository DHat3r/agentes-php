<?php

namespace App\Agent;

use App\Models\Evaluation;
use App\Models\Question;

/**
 * Estado compartido por las herramientas durante una ejecución del agente.
 *
 * Guarda qué respuestas ya calificó el juez, para que guardar_resultado solo
 * acepte una respuesta evaluada y use sus puntajes reales (no los que diga el modelo).
 */
final class EvaluationContext
{
    /** @var array<string, JudgeScores> respuesta => puntajes */
    private array $judged = [];

    public function __construct(
        public readonly Evaluation $evaluation,
        public readonly int $maxJudgeAttempts = 3,
    ) {}

    public function question(): Question
    {
        return $this->evaluation->question;
    }

    public function remember(string $answer, JudgeScores $scores): void
    {
        $this->judged[$this->key($answer)] = $scores;
        $this->evaluation->increment('attempts');
    }

    public function scoresFor(string $answer): ?JudgeScores
    {
        return $this->judged[$this->key($answer)] ?? null;
    }

    public function remainingAttempts(): int
    {
        return max(0, $this->maxJudgeAttempts - count($this->judged));
    }

    /** Normaliza espacios para que diferencias de formato no cuenten como otra respuesta. */
    private function key(string $answer): string
    {
        return preg_replace('/\s+/', ' ', trim($answer));
    }
}
