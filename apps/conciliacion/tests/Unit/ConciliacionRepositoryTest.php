<?php

declare(strict_types=1);

namespace Conciliacion\Tests\Unit;

use Conciliacion\Persistencia\Database;
use Conciliacion\Tests\Support\ConBaseDeDatos;
use PHPUnit\Framework\TestCase;

final class ConciliacionRepositoryTest extends TestCase
{
    use ConBaseDeDatos;

    protected function setUp(): void
    {
        $this->prepararBaseDeDatos();
    }

    public function test_las_migraciones_no_se_aplican_dos_veces(): void
    {
        $this->assertSame([], Database::migrar($this->pdo, __DIR__.'/../../migrations'));
    }

    public function test_crea_una_conciliacion_con_movimientos_y_facturas_pendientes(): void
    {
        $id = $this->repo->crear([$this->movimiento('ABONO', 1000)], [$this->factura('F-1', 'Ana', 1000)]);

        $detalle = $this->repo->detalle($id);
        $this->assertSame(['total_movimientos' => 1, 'conciliados' => 0, 'en_revision' => 0, 'pendientes' => 1], $detalle['resumen']);
        $this->assertSame(1000, $detalle['movimientos'][0]['monto']);
        $this->assertFalse($detalle['facturas'][0]['usada']);
    }

    public function test_conciliar_marca_el_movimiento_y_usa_la_factura(): void
    {
        $id = $this->repo->crear([$this->movimiento('ABONO', 1000)], [$this->factura('F-1', 'Ana', 1000)]);
        $movimiento = $this->repo->movimientosPendientes($id)[0];
        $factura = $this->repo->facturasDisponibles($id)[0];

        $this->repo->conciliar($movimiento['id'], $factura['id'], 'Calza', 'agente');

        $detalle = $this->repo->detalle($id);
        $this->assertSame('conciliado', $detalle['movimientos'][0]['estado']);
        $this->assertSame('F-1', $detalle['movimientos'][0]['factura_numero']);
        $this->assertSame([], $this->repo->facturasDisponibles($id));
    }

    public function test_filtra_facturas_por_rango_de_monto_y_cliente(): void
    {
        $id = $this->repo->crear([], [
            $this->factura('F-1', 'Panadería La Espiga', 500),
            $this->factura('F-2', 'Ferretería Sur', 1500),
            $this->factura('F-3', 'Panadería La Espiga', 2500),
        ]);

        $this->assertSame(['F-2', 'F-3'], array_column($this->repo->facturasDisponibles($id, montoMin: 1000), 'numero'));
        $this->assertSame(['F-1'], array_column($this->repo->facturasDisponibles($id, montoMax: 1000, cliente: 'espiga'), 'numero'));
    }

    public function test_no_mezcla_datos_de_distintas_conciliaciones(): void
    {
        $primera = $this->repo->crear([$this->movimiento('A', 1)], [$this->factura('F-1', 'Ana', 1)]);
        $segunda = $this->repo->crear([$this->movimiento('B', 2)], [$this->factura('F-2', 'Beto', 2)]);

        $movimientoDeLaPrimera = $this->repo->movimientosPendientes($primera)[0]['id'];
        $this->assertNull($this->repo->movimiento($segunda, $movimientoDeLaPrimera));
        $this->assertSame(['F-2'], array_column($this->repo->facturasDisponibles($segunda), 'numero'));
    }

    public function test_lista_conciliaciones_con_conteos(): void
    {
        $id = $this->repo->crear([$this->movimiento('A', 1), $this->movimiento('B', 2)], []);
        $this->repo->enviarARevision($this->repo->movimientosPendientes($id)[0]['id'], 'Dudoso', 'agente');

        $lista = $this->repo->listar();
        $this->assertSame(['total_movimientos' => 2, 'conciliados' => 0, 'en_revision' => 1, 'pendientes' => 1],
            array_intersect_key($lista[0], array_flip(['total_movimientos', 'conciliados', 'en_revision', 'pendientes'])));
    }

    public function test_detalle_de_una_conciliacion_inexistente_es_null(): void
    {
        $this->assertNull($this->repo->detalle(99));
    }
}
