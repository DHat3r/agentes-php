<?php

declare(strict_types=1);

namespace Conciliacion\Agente\Herramientas;

use Agentes\Core\Tools\Tool;
use Conciliacion\Persistencia\ConciliacionRepository;

final class ListarMovimientosPendientes implements Tool
{
    public function __construct(
        private readonly ConciliacionRepository $repo,
        private readonly int $conciliacionId,
    ) {}

    public function name(): string
    {
        return 'listar_movimientos_pendientes';
    }

    public function description(): string
    {
        return 'Lista los abonos de la cartola que todavía no se concilian ni se enviaron a revisión.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass];
    }

    public function execute(array $arguments): array
    {
        return ['movimientos' => $this->repo->movimientosPendientes($this->conciliacionId)];
    }
}
