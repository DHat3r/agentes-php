<?php

declare(strict_types=1);

namespace Agentes\Core\Tests\Support;

use Agentes\Core\Exceptions\ToolException;
use Agentes\Core\Tools\Tool;

/** Herramienta de prueba: suma dos enteros y rechaza números negativos. */
final class SumTool implements Tool
{
    public int $executions = 0;

    public function name(): string
    {
        return 'sumar';
    }

    public function description(): string
    {
        return 'Suma dos enteros no negativos.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'a' => ['type' => 'integer'],
                'b' => ['type' => 'integer'],
            ],
            'required' => ['a', 'b'],
        ];
    }

    public function execute(array $arguments): array
    {
        $this->executions++;
        if ($arguments['a'] < 0 || $arguments['b'] < 0) {
            throw new ToolException('Solo acepto números no negativos.');
        }

        return ['resultado' => $arguments['a'] + $arguments['b']];
    }
}
