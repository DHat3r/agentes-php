<?php

declare(strict_types=1);

namespace Conciliacion\Agente\Herramientas;

use Agentes\Core\Exceptions\ToolException;
use Agentes\Core\Tools\Tool;
use Conciliacion\Persistencia\ConciliacionRepository;

/**
 * Deja un movimiento para que lo revise una persona: es la salida segura ante la duda.
 */
final class EnviarARevision implements Tool
{
    public function __construct(
        private readonly ConciliacionRepository $repo,
        private readonly int $conciliacionId,
    ) {}

    public function name(): string
    {
        return 'enviar_a_revision';
    }

    public function description(): string
    {
        return 'Envía un movimiento a revisión humana cuando no hay factura, hay varias posibles o es un pago parcial.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'movimiento_id' => ['type' => 'integer', 'minimum' => 1],
                'motivo' => ['type' => 'string', 'minLength' => 5, 'description' => 'Qué debe revisar la persona'],
            ],
            'required' => ['movimiento_id', 'motivo'],
        ];
    }

    public function execute(array $arguments): array
    {
        $movimiento = $this->repo->movimiento($this->conciliacionId, $arguments['movimiento_id']);
        if ($movimiento === null || $movimiento['estado'] !== 'pendiente') {
            throw new ToolException("El movimiento {$arguments['movimiento_id']} no existe o ya fue procesado.");
        }

        $this->repo->enviarARevision($movimiento['id'], $arguments['motivo'], 'agente');

        return ['en_revision' => true];
    }
}
