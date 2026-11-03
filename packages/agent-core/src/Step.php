<?php

declare(strict_types=1);

namespace Agentes\Core;

/**
 * Un paso de la traza del agente. Las apps la guardan y el frontend la muestra,
 * así se puede ver por qué el agente llegó a su respuesta.
 */
final readonly class Step
{
    private function __construct(
        public string $type,
        public ?string $tool,
        public array $arguments,
        public string $content,
    ) {}

    public static function toolCall(string $tool, array $arguments): self
    {
        return new self('tool_call', $tool, $arguments, '');
    }

    public static function toolResult(string $tool, string $content): self
    {
        return new self('tool_result', $tool, [], $content);
    }

    public static function finalAnswer(string $content): self
    {
        return new self('final', null, [], $content);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'tool' => $this->tool,
            'arguments' => $this->arguments,
            'content' => $this->content,
        ];
    }
}
