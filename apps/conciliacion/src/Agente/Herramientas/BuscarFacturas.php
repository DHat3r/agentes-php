<?php

declare(strict_types=1);

namespace Conciliacion\Agente\Herramientas;

use Agentes\Core\Tools\Tool;
use Conciliacion\Persistencia\ConciliacionRepository;

final class BuscarFacturas implements Tool
{
    public function __construct(
        private readonly ConciliacionRepository $repo,
        private readonly int $conciliacionId,
    ) {}

    public function name(): string
    {
        return 'buscar_facturas';
    }

    public function description(): string
    {
        return 'Busca facturas aún no pagadas. Filtra por rango de monto en pesos y/o por parte del nombre del cliente.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'monto_min' => ['type' => 'integer', 'minimum' => 0],
                'monto_max' => ['type' => 'integer', 'minimum' => 0],
                'cliente' => ['type' => 'string', 'description' => 'Parte del nombre, sin importar mayúsculas'],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        return ['facturas' => $this->repo->facturasDisponibles(
            $this->conciliacionId,
            $arguments['monto_min'] ?? null,
            $arguments['monto_max'] ?? null,
            $arguments['cliente'] ?? null,
        )];
    }
}
