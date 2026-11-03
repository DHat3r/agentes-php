<?php

namespace App\Agent;

/**
 * Puntajes del juez, todos entre 0 y 1.
 */
final readonly class JudgeScores
{
    /** Pesos del puntaje general, los mismos de la versión original en Python. */
    public const WEIGHTS = ['similarity' => 0.5, 'quality' => 0.3, 'completeness' => 0.2];

    public const APPROVAL_THRESHOLD = 0.7;

    public float $overall;

    public function __construct(
        public float $similarity,
        public float $quality,
        public float $completeness,
    ) {
        $this->overall = round(
            $similarity * self::WEIGHTS['similarity']
            + $quality * self::WEIGHTS['quality']
            + $completeness * self::WEIGHTS['completeness'],
            4,
        );
    }

    public function approved(): bool
    {
        return $this->overall >= self::APPROVAL_THRESHOLD;
    }

    public function toArray(): array
    {
        return [
            'similarity_score' => $this->similarity,
            'quality_score' => $this->quality,
            'completeness_score' => $this->completeness,
            'overall_score' => $this->overall,
        ];
    }
}
