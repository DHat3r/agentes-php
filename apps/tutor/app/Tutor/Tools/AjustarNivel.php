<?php

namespace App\Tutor\Tools;

use Agentes\Core\Exceptions\ToolException;
use Agentes\Core\Tools\Tool;
use App\Tutor\GradingContext;
use App\Tutor\LevelPolicy;

/**
 * Aplica la regla de nivel. El modelo solo decide cuándo llamarla; el cálculo es de LevelPolicy.
 */
final class AjustarNivel implements Tool
{
    public function __construct(
        private readonly GradingContext $context,
        private readonly LevelPolicy $policy,
    ) {}

    public function name(): string
    {
        return 'ajustar_nivel';
    }

    public function description(): string
    {
        return 'Actualiza el nivel del estudiante según el resultado de la corrección. Úsala después de corregir.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass];
    }

    public function execute(array $arguments): array
    {
        $exercise = $this->context->exercise;
        if (! $this->context->isGraded()) {
            throw new ToolException('Primero corrige el ejercicio con la herramienta corregir.');
        }
        if ($exercise->level_adjusted) {
            throw new ToolException('El nivel ya se ajustó para este ejercicio.');
        }

        $progress = $exercise->student->progressFor($exercise->topic);
        $this->context->previousLevel = $progress->level;
        $this->context->newLevel = $this->policy->apply($progress, $exercise->correct)->level;
        $exercise->update(['level_adjusted' => true]);

        return ['nivel_anterior' => $this->context->previousLevel, 'nivel_nuevo' => $this->context->newLevel];
    }
}
