<?php

namespace App\Agent\Tools;

use Agentes\Core\Exceptions\ToolException;
use Agentes\Core\Tools\Tool;
use App\Agent\EvaluationContext;

/**
 * Guarda la respuesta final con los puntajes que le dio el juez.
 */
final class GuardarResultado implements Tool
{
    public function __construct(private readonly EvaluationContext $context) {}

    public function name(): string
    {
        return 'guardar_resultado';
    }

    public function description(): string
    {
        return 'Guarda tu respuesta final. Debe ser exactamente una respuesta que ya calificaste con evaluar_respuesta.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'respuesta' => ['type' => 'string', 'minLength' => 10],
            ],
            'required' => ['respuesta'],
        ];
    }

    public function execute(array $arguments): array
    {
        $scores = $this->context->scoresFor($arguments['respuesta']);
        if ($scores === null) {
            throw new ToolException('Esa respuesta no fue evaluada. Usa evaluar_respuesta antes de guardarla.');
        }

        $this->context->evaluation->complete(trim($arguments['respuesta']), $scores);

        return ['guardado' => true, 'overall_score' => $scores->overall];
    }
}
