<?php

namespace App\Tutor\Tools;

use Agentes\Core\Tools\Tool;
use App\Tutor\GradingContext;

final class ObtenerEjercicio implements Tool
{
    public function __construct(private readonly GradingContext $context) {}

    public function name(): string
    {
        return 'obtener_ejercicio';
    }

    public function description(): string
    {
        return 'Devuelve el enunciado, la respuesta esperada y la respuesta del estudiante.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass];
    }

    public function execute(array $arguments): array
    {
        $exercise = $this->context->exercise;

        return [
            'tema' => $exercise->topic,
            'nivel' => $exercise->level,
            'enunciado' => $exercise->statement,
            'respuesta_esperada' => $exercise->expected_answer,
            'respuesta_estudiante' => $exercise->student_answer,
        ];
    }
}
