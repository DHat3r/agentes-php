<?php

declare(strict_types=1);

namespace Agentes\Core\Llm;

use Agentes\Core\Exceptions\LlmException;

/**
 * Una respuesta del modelo: texto final, pedidos de herramientas, o ambos.
 */
final readonly class LlmResponse
{
    /**
     * @param  list<ToolCall>  $toolCalls
     */
    public function __construct(
        public ?string $content,
        public array $toolCalls = [],
    ) {}

    /**
     * Construye la respuesta desde el `message` que devuelve una API compatible con OpenAI.
     */
    public static function fromOpenAiMessage(array $message): self
    {
        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            if (! isset($call['id'], $call['function']['name'])) {
                throw new LlmException('El modelo pidió una herramienta con un formato inválido.');
            }
            $toolCalls[] = new ToolCall(
                $call['id'],
                $call['function']['name'],
                (string) ($call['function']['arguments'] ?? '{}'),
            );
        }

        $content = $message['content'] ?? null;

        return new self(is_string($content) ? $content : null, $toolCalls);
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    /**
     * Mensaje que se agrega al historial para que el modelo recuerde qué pidió.
     */
    public function toAssistantMessage(): array
    {
        $message = ['role' => 'assistant', 'content' => $this->content];
        if ($this->hasToolCalls()) {
            $message['tool_calls'] = array_map(fn (ToolCall $call) => $call->toArray(), $this->toolCalls);
        }

        return $message;
    }
}
