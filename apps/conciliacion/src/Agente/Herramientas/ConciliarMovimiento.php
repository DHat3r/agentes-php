<?php

declare(strict_types=1);

namespace Conciliacion\Agente\Herramientas;

use Agentes\Core\Exceptions\ToolException;
use Agentes\Core\Tools\Tool;
use Conciliacion\Persistencia\ConciliacionRepository;

/**
 * Une un abono con una factura. Las reglas contables se validan aquí, no en el prompt:
 * aunque el modelo se equivoque, nunca se concilian montos distintos ni facturas ya usadas.
 */
final class ConciliarMovimiento implements Tool
{
    public function __construct(
        private readonly ConciliacionRepository $repo,
        private readonly int $conciliacionId,
    ) {}

    public function name(): string
    {
        return 'conciliar_movimiento';
    }

    public function description(): string
    {
        return 'Concilia un movimiento con una factura. Solo si el monto es exactamente igual y estás seguro del cliente.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'movimiento_id' => ['type' => 'integer', 'minimum' => 1],
                'factura_id' => ['type' => 'integer', 'minimum' => 1],
                'motivo' => ['type' => 'string', 'minLength' => 5, 'description' => 'Por qué calzan'],
            ],
            'required' => ['movimiento_id', 'factura_id', 'motivo'],
        ];
    }

    public function execute(array $arguments): array
    {
        $movimiento = $this->repo->movimiento($this->conciliacionId, $arguments['movimiento_id']);
        if ($movimiento === null || $movimiento['estado'] !== 'pendiente') {
            throw new ToolException("El movimiento {$arguments['movimiento_id']} no existe o ya fue procesado.");
        }

        $factura = $this->repo->factura($this->conciliacionId, $arguments['factura_id']);
        if ($factura === null || $factura['usada'] === 1) {
            throw new ToolException("La factura {$arguments['factura_id']} no existe o ya está pagada.");
        }

        if ($movimiento['monto'] !== $factura['monto']) {
            throw new ToolException(sprintf(
                'Los montos no coinciden (abono %d, factura %d). Si es un pago parcial o dudoso, usa enviar_a_revision.',
                $movimiento['monto'],
                $factura['monto'],
            ));
        }

        $this->repo->conciliar($movimiento['id'], $factura['id'], $arguments['motivo'], 'agente');

        return ['conciliado' => true, 'factura' => $factura['numero']];
    }
}
