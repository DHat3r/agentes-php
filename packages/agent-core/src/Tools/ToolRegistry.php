<?php

declare(strict_types=1);

namespace Agentes\Core\Tools;

use InvalidArgumentException;

/**
 * Catálogo de herramientas disponibles para un agente.
 */
final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function __construct(Tool ...$tools)
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(Tool $tool): void
    {
        if (isset($this->tools[$tool->name()])) {
            throw new InvalidArgumentException("La herramienta '{$tool->name()}' ya está registrada.");
        }
        $this->tools[$tool->name()] = $tool;
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Definiciones en el formato `tools` de la API de chat de OpenAI/OpenRouter.
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return array_values(array_map(fn (Tool $tool) => [
            'type' => 'function',
            'function' => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
        ], $this->tools));
    }
}
