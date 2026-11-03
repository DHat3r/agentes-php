<?php

declare(strict_types=1);

namespace Agentes\Core;

use Agentes\Core\Exceptions\LlmException;
use Agentes\Core\Exceptions\MaxIterationsReached;
use Agentes\Core\Exceptions\ToolException;
use Agentes\Core\Llm\LlmClient;
use Agentes\Core\Llm\ToolCall;
use Agentes\Core\Tools\SchemaValidator;
use Agentes\Core\Tools\ToolRegistry;

/**
 * Ciclo de agente con tool calling:
 *
 *   1. Se envía el historial y las herramientas al modelo.
 *   2. Si el modelo pide herramientas, se ejecutan y sus resultados se agregan al historial.
 *   3. Se repite hasta que el modelo responda sin pedir herramientas, o hasta el límite.
 *
 * Los errores del modelo (herramienta inexistente, argumentos inválidos, reglas de negocio)
 * se le devuelven como resultado para que se corrija solo. Los errores de red (LlmException)
 * escapan, porque reintentar es decisión de quien llama (por ejemplo, una cola).
 */
final class Agent
{
    private readonly SchemaValidator $validator;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly ToolRegistry $tools,
        private readonly int $maxIterations = 8,
    ) {
        $this->validator = new SchemaValidator;
    }

    /**
     * @throws LlmException si el modelo falla o responde vacío.
     * @throws MaxIterationsReached si no termina dentro del límite.
     */
    public function run(string $systemPrompt, string $userMessage): AgentResult
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ];
        $steps = [];

        for ($iteration = 1; $iteration <= $this->maxIterations; $iteration++) {
            $response = $this->llm->chat($messages, $this->tools->definitions());
            $messages[] = $response->toAssistantMessage();

            if (! $response->hasToolCalls()) {
                $answer = trim($response->content ?? '');
                if ($answer === '') {
                    throw new LlmException('El modelo respondió vacío, sin texto ni herramientas.');
                }
                $steps[] = Step::finalAnswer($answer);

                return new AgentResult($answer, $steps, $iteration);
            }

            foreach ($response->toolCalls as $call) {
                [$arguments, $result] = $this->executeTool($call);
                $steps[] = Step::toolCall($call->name, $arguments);
                $steps[] = Step::toolResult($call->name, $result);
                $messages[] = ['role' => 'tool', 'tool_call_id' => $call->id, 'content' => $result];
            }
        }

        throw new MaxIterationsReached($this->maxIterations, $steps);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string} Argumentos decodificados y resultado en JSON.
     */
    private function executeTool(ToolCall $call): array
    {
        $tool = $this->tools->get($call->name);
        if ($tool === null) {
            return [[], $this->error("La herramienta '{$call->name}' no existe.")];
        }

        $arguments = json_decode($call->rawArguments === '' ? '{}' : $call->rawArguments, true);
        if (! is_array($arguments)) {
            return [[], $this->error('Los argumentos no son un objeto JSON válido.')];
        }

        $errors = $this->validator->validate($tool->parameters(), $arguments);
        if ($errors !== []) {
            return [$arguments, $this->error(implode(' ', $errors))];
        }

        try {
            return [$arguments, $this->encode($tool->execute($arguments))];
        } catch (ToolException $e) {
            return [$arguments, $this->error($e->getMessage())];
        }
    }

    private function error(string $message): string
    {
        return $this->encode(['error' => $message]);
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
