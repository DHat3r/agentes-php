<?php

namespace App\Tutor;

use App\Models\Progress;

/**
 * Regla para subir o bajar de nivel. Es determinista a propósito: el LLM decide si
 * la respuesta está bien, pero el nivel no depende de su "opinión", así es predecible y testeable.
 *
 * - 3 respuestas correctas seguidas → sube un nivel (máximo 5).
 * - 2 incorrectas seguidas → baja un nivel (mínimo 1).
 */
final class LevelPolicy
{
    public const MIN_LEVEL = 1;

    public const MAX_LEVEL = 5;

    public const STREAK_TO_LEVEL_UP = 3;

    public const STREAK_TO_LEVEL_DOWN = 2;

    public function apply(Progress $progress, bool $correct): Progress
    {
        if ($correct) {
            $progress->correct_streak++;
            $progress->wrong_streak = 0;
            if ($progress->correct_streak >= self::STREAK_TO_LEVEL_UP && $progress->level < self::MAX_LEVEL) {
                $progress->level++;
                $progress->correct_streak = 0;
            }
        } else {
            $progress->wrong_streak++;
            $progress->correct_streak = 0;
            if ($progress->wrong_streak >= self::STREAK_TO_LEVEL_DOWN && $progress->level > self::MIN_LEVEL) {
                $progress->level--;
                $progress->wrong_streak = 0;
            }
        }

        $progress->save();

        return $progress;
    }
}
