<?php

namespace App\Tutor\Tools;

use Agentes\Core\Exceptions\ToolException;
use Agentes\Core\Tools\Tool;
use App\Tutor\GradingContext;

final class Corregir implements Tool
{
    public function __construct(private readonly GradingContext $context) {}

    public function name(): string
    {
        return 'corregir';
    }

    public function description(): string
    {
        return 'Registra si la respuesta es correcta y una retroalimentación breve y amable para el estudiante.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'correcta' => ['type' => 'boolean'],
                'retroalimentacion' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 1000],
            ],
            'required' => ['correcta', 'retroalimentacion'],
        ];
    }

    public function execute(array $arguments): array
    {
        if ($this->context->isGraded()) {
            throw new ToolException('Este ejercicio ya fue corregido.');
        }

        $this->context->exercise->update([
            'correct' => $arguments['correcta'],
            'feedback' => trim($arguments['retroalimentacion']),
            'answered_at' => now(),
        ]);

        return ['corregido' => true, 'siguiente_paso' => 'Llama a ajustar_nivel.'];
    }
}
