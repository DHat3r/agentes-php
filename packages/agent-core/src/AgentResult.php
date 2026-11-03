<?php

declare(strict_types=1);

namespace Agentes\Core;

final readonly class AgentResult
{
    /**
     * @param  list<Step>  $steps
     */
    public function __construct(
        public string $answer,
        public array $steps,
        public int $iterations,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function trace(): array
    {
        return array_map(fn (Step $step) => $step->toArray(), $this->steps);
    }

    /**
     * Cuántas veces se pidió cada herramienta; útil en tests y métricas.
     */
    public function toolCallCount(string $tool): int
    {
        return count(array_filter(
            $this->steps,
            fn (Step $step) => $step->type === 'tool_call' && $step->tool === $tool,
        ));
    }
}
