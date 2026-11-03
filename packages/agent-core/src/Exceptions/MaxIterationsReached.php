<?php

declare(strict_types=1);

namespace Agentes\Core\Exceptions;

use Agentes\Core\Step;
use RuntimeException;

/**
 * El modelo siguió pidiendo herramientas sin llegar a una respuesta final.
 * Guarda los pasos para poder mostrar o persistir qué alcanzó a hacer.
 */
final class MaxIterationsReached extends RuntimeException
{
    /**
     * @param  list<Step>  $steps
     */
    public function __construct(public readonly int $maxIterations, public readonly array $steps)
    {
        parent::__construct("El agente superó el límite de {$maxIterations} iteraciones.");
    }
}
