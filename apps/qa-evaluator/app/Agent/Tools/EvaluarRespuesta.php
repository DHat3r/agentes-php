<?php

namespace App\Agent\Tools;

use Agentes\Core\Exceptions\ToolException;
use Agentes\Core\Tools\Tool;
use App\Agent\AnswerJudge;
use App\Agent\EvaluationContext;

/**
 * Pide al juez que califique una respuesta candidata contra la respuesta humana.
 * Limita los intentos para que el agente no gaste llamadas indefinidamente.
 */
final class EvaluarRespuesta implements Tool
{
    public function __construct(
        private readonly EvaluationContext $context,
        private readonly AnswerJudge $judge,
    ) {}

    public function name(): string
    {
        return 'evaluar_respuesta';
    }

    public function description(): string
    {
        return 'Califica tu respuesta comparándola con la respuesta humana de referencia. '
            .'Devuelve puntajes de 0 a 1 y si quedó aprobada (general >= 0.7). Tiene intentos limitados.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'respuesta' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 4000],
            ],
            'required' => ['respuesta'],
        ];
    }

    public function execute(array $arguments): array
    {
        if ($this->context->remainingAttempts() === 0) {
            throw new ToolException('Ya no quedan intentos de evaluación. Guarda la mejor respuesta evaluada.');
        }

        $question = $this->context->question();
        $scores = $this->judge->judge($question->question_text, $question->human_answer, $arguments['respuesta']);
        $this->context->remember($arguments['respuesta'], $scores);

        return [
            ...$scores->toArray(),
            'aprobada' => $scores->approved(),
            'intentos_restantes' => $this->context->remainingAttempts(),
        ];
    }
}
