<?php

declare(strict_types=1);

namespace Conciliacion;

use Agentes\Core\Exceptions\LlmException;
use Agentes\Core\Exceptions\MaxIterationsReached;
use Agentes\Core\Step;
use Conciliacion\Agente\AgenteConciliador;
use Conciliacion\Dominio\LectorCsv;
use Conciliacion\Dominio\ReglaReferenciaExacta;
use Conciliacion\Persistencia\ConciliacionRepository;

/**
 * Caso de uso completo: CSV → regla determinista → agente → revisión humana de lo que quede.
 *
 * Si el modelo falla, la conciliación no se pierde: lo resuelto por la regla queda
 * guardado y el resto pasa a revisión con el motivo del error.
 */
final class ProcesarConciliacion
{
    public function __construct(
        private readonly LectorCsv $lector,
        private readonly ConciliacionRepository $repo,
        private readonly ReglaReferenciaExacta $regla,
        private readonly AgenteConciliador $agente,
    ) {}

    public function __invoke(string $cartolaCsv, string $facturasCsv): int
    {
        // Se leen ambos antes de guardar nada: si un archivo es inválido no queda una conciliación a medias.
        $movimientos = $this->lector->cartola($cartolaCsv);
        $facturas = $this->lector->facturas($facturasCsv);

        $id = $this->repo->crear($movimientos, $facturas);
        $this->regla->aplicar($id);

        if ($this->repo->movimientosPendientes($id) === []) {
            return $id;
        }

        $error = null;
        $traza = [];
        try {
            $traza = $this->agente->ejecutar($id)->trace();
        } catch (MaxIterationsReached $e) {
            $error = $e->getMessage();
            $traza = array_map(fn (Step $paso) => $paso->toArray(), $e->steps);
        } catch (LlmException $e) {
            $error = $e->getMessage();
        }

        foreach ($this->repo->movimientosPendientes($id) as $movimiento) {
            $motivo = $error === null ? 'El agente no alcanzó a resolverlo.' : "Sin revisar por un error del modelo: {$error}";
            $this->repo->enviarARevision($movimiento['id'], $motivo, 'sistema');
        }
        $this->repo->guardarResultadoAgente($id, $traza, $error);

        return $id;
    }
}
