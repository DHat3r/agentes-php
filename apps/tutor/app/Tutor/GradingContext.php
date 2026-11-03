<?php

namespace App\Tutor;

use App\Models\Exercise;

/** Estado compartido por las herramientas mientras el agente corrige una respuesta. */
final class GradingContext
{
    public ?int $previousLevel = null;

    public ?int $newLevel = null;

    public function __construct(public readonly Exercise $exercise) {}

    public function isGraded(): bool
    {
        return $this->exercise->correct !== null;
    }
}
