<?php

declare(strict_types=1);

namespace Agentes\Core\Llm;

use Agentes\Core\Exceptions\LlmException;
use Closure;
use Throwable;

/**
 * Modelo simulado para tests: devuelve un guion de respuestas en orden.
 *
 * Cada elemento del guion puede ser una LlmResponse, una excepción (para simular
 * timeouts) o un Closure que recibe el historial y decide qué responder.
 * Así los tests son deterministas y en CI no se llama a la API real.
 */
final class FakeLlmClient implements LlmClient
{
    /** @var list<LlmResponse|Throwable|Closure> */
    private array $script;

    /** @var list<array{messages: array, tools: array}> */
    public array $calls = [];

    public function __construct(LlmResponse|Throwable|Closure ...$script)
    {
        $this->script = $script;
    }

    public static function answer(string $text): LlmResponse
    {
        return new LlmResponse($text);
    }

    public static function toolCall(string $name, array $arguments, ?string $id = null): LlmResponse
    {
        $json = json_encode($arguments === [] ? new \stdClass : $arguments, JSON_UNESCAPED_UNICODE);

        return new LlmResponse(null, [new ToolCall($id ?? 'call_'.$name, $name, $json)]);
    }

    public function push(LlmResponse|Throwable|Closure ...$responses): void
    {
        array_push($this->script, ...$responses);
    }

    public function chat(array $messages, array $tools = []): LlmResponse
    {
        $this->calls[] = ['messages' => $messages, 'tools' => $tools];

        $next = array_shift($this->script);
        if ($next === null) {
            throw new LlmException('FakeLlmClient se quedó sin respuestas en el guion.');
        }
        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next instanceof Closure ? $next($messages, $tools) : $next;
    }

    /**
     * Último mensaje con rol `tool`: lo que el agente le devolvió al modelo.
     */
    public function lastToolResult(): ?array
    {
        $last = end($this->calls);
        if ($last === false) {
            return null;
        }
        foreach (array_reverse($last['messages']) as $message) {
            if ($message['role'] === 'tool') {
                return json_decode($message['content'], true);
            }
        }

        return null;
    }
}
