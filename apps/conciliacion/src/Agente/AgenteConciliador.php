<?php

declare(strict_types=1);

namespace Conciliacion\Agente;

use Agentes\Core\Agent;
use Agentes\Core\AgentResult;
use Agentes\Core\Llm\LlmClient;
use Agentes\Core\Tools\ToolRegistry;
use Conciliacion\Agente\Herramientas\BuscarFacturas;
use Conciliacion\Agente\Herramientas\ConciliarMovimiento;
use Conciliacion\Agente\Herramientas\EnviarARevision;
use Conciliacion\Agente\Herramientas\ListarMovimientosPendientes;
use Conciliacion\Persistencia\ConciliacionRepository;

final class AgenteConciliador
{
    private const PROMPT_SISTEMA = <<<'PROMPT'
        Eres un asistente contable chileno que concilia abonos bancarios con facturas de venta.

        Para cada movimiento pendiente:
        1. Busca facturas con exactamente el mismo monto. Si la glosa menciona un cliente, úsalo para filtrar.
        2. Concilia solo si hay UNA factura que calza en monto y el cliente es coherente con la glosa.
        3. Envía a revisión, explicando el motivo, cuando no hay factura, hay varias posibles
           o el monto no coincide (por ejemplo, un pago parcial).
        Nunca inventes ids: usa solo los que devuelven las herramientas.
        Cuando no queden movimientos pendientes, termina con un resumen breve.
        PROMPT;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly ConciliacionRepository $repo,
        private readonly int $maxIteraciones = 15,
    ) {}

    public function ejecutar(int $conciliacionId): AgentResult
    {
        $herramientas = new ToolRegistry(
            new ListarMovimientosPendientes($this->repo, $conciliacionId),
            new BuscarFacturas($this->repo, $conciliacionId),
            new ConciliarMovimiento($this->repo, $conciliacionId),
            new EnviarARevision($this->repo, $conciliacionId),
        );

        return (new Agent($this->llm, $herramientas, $this->maxIteraciones))
            ->run(self::PROMPT_SISTEMA, 'Concilia los movimientos pendientes.');
    }
}
