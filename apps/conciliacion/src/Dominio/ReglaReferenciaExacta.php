<?php

declare(strict_types=1);

namespace Conciliacion\Dominio;

use Conciliacion\Persistencia\ConciliacionRepository;

/**
 * Regla determinista que corre antes del agente: si la glosa del banco menciona el número
 * de una factura disponible y el monto es exactamente el mismo, se concilia sin usar el LLM.
 *
 * Lo determinista primero, el modelo solo para lo ambiguo: es más barato, más rápido
 * y no se equivoca en los casos obvios.
 */
final class ReglaReferenciaExacta
{
    public function __construct(private readonly ConciliacionRepository $repo) {}

    /** @return int Cantidad de movimientos conciliados por la regla. */
    public function aplicar(int $conciliacionId): int
    {
        $conciliados = 0;
        foreach ($this->repo->movimientosPendientes($conciliacionId) as $movimiento) {
            $candidatas = array_filter(
                $this->repo->facturasDisponibles($conciliacionId, $movimiento['monto'], $movimiento['monto']),
                fn (array $factura) => $this->mencionaNumero($movimiento['descripcion'], $factura['numero']),
            );

            if (count($candidatas) === 1) {
                $factura = array_values($candidatas)[0];
                $this->repo->conciliar($movimiento['id'], $factura['id'], "La glosa menciona {$factura['numero']} y el monto coincide.", 'regla');
                $conciliados++;
            }
        }

        return $conciliados;
    }

    /** Coincidencia como palabra completa: "F-10" no debe calzar con "F-1001". */
    private function mencionaNumero(string $descripcion, string $numero): bool
    {
        return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($numero, '/').'(?![\p{L}\p{N}])/iu', $descripcion) === 1;
    }
}
