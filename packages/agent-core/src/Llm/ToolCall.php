<?php

declare(strict_types=1);

namespace Agentes\Core\Llm;

/**
 * Pedido del modelo para ejecutar una herramienta.
 *
 * Los argumentos llegan como texto JSON y sin validar: el modelo puede equivocarse,
 * así que el agente los decodifica y valida antes de ejecutar nada.
 */
final readonly class ToolCall
{
    public function __construct(
        public string $id,
        public string $name,
        public string $rawArguments,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => ['name' => $this->name, 'arguments' => $this->rawArguments],
        ];
    }
}
