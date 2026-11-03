<?php

namespace App\Agent\Tools;

use Agentes\Core\Exceptions\ToolException;
use Agentes\Core\Tools\Tool;
use App\Models\Question;

/**
 * Devuelve el texto de una pregunta. No incluye la respuesta humana a propósito:
 * si el modelo la viera, la copiaría y la evaluación no mediría nada.
 */
final class BuscarPregunta implements Tool
{
    public function name(): string
    {
        return 'buscar_pregunta';
    }

    public function description(): string
    {
        return 'Obtiene el texto y la categoría de una pregunta a partir de su id.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'pregunta_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Id de la pregunta'],
            ],
            'required' => ['pregunta_id'],
        ];
    }

    public function execute(array $arguments): array
    {
        $question = Question::find($arguments['pregunta_id']);
        if ($question === null) {
            throw new ToolException("No existe la pregunta {$arguments['pregunta_id']}.");
        }

        return [
            'id' => $question->id,
            'pregunta' => $question->question_text,
            'categoria' => $question->category,
        ];
    }
}
