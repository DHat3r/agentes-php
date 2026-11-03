<?php

namespace App\Tutor\Tools;

use Agentes\Core\Tools\Tool;
use App\Tutor\GenerationContext;
use App\Tutor\Topics;

final class ConsultarProgreso implements Tool
{
    public function __construct(private readonly GenerationContext $context) {}

    public function name(): string
    {
        return 'consultar_progreso';
    }

    public function description(): string
    {
        return 'Devuelve el nivel actual del estudiante en el tema, sus rachas, la guía de niveles y sus últimos ejercicios para no repetirlos.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass];
    }

    public function execute(array $arguments): array
    {
        $progress = $this->context->progress();

        return [
            'tema' => $this->context->topic,
            'nivel' => $progress->level,
            'racha_correctas' => $progress->correct_streak,
            'racha_incorrectas' => $progress->wrong_streak,
            'guia_niveles' => Topics::guide($this->context->topic),
            'ejercicios_recientes' => $this->context->student->exercises()
                ->where('topic', $this->context->topic)
                ->latest('id')
                ->limit(3)
                ->pluck('statement')
                ->all(),
        ];
    }
}
