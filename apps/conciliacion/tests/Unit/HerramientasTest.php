<?php

declare(strict_types=1);

namespace Conciliacion\Tests\Unit;

use Agentes\Core\Exceptions\ToolException;
use Conciliacion\Agente\Herramientas\BuscarFacturas;
use Conciliacion\Agente\Herramientas\ConciliarMovimiento;
use Conciliacion\Agente\Herramientas\EnviarARevision;
use Conciliacion\Agente\Herramientas\ListarMovimientosPendientes;
use Conciliacion\Tests\Support\ConBaseDeDatos;
use PHPUnit\Framework\TestCase;

final class HerramientasTest extends TestCase
{
    use ConBaseDeDatos;

    private int $id;

    private int $movimientoId;

    protected function setUp(): void
    {
        $this->prepararBaseDeDatos();
        $this->id = $this->repo->crear(
            [$this->movimiento('ABONO PANADERIA LA ESPIGA', 357000)],
            [$this->factura('F-2', 'Panadería La Espiga', 357000), $this->factura('F-3', 'Ferretería Sur', 1000000)],
        );
        $this->movimientoId = $this->repo->movimientosPendientes($this->id)[0]['id'];
    }

    private function facturaId(string $numero): int
    {
        $facturas = array_column($this->repo->facturasDisponibles($this->id), 'id', 'numero');

        return $facturas[$numero];
    }

    public function test_listar_devuelve_los_movimientos_pendientes(): void
    {
        $resultado = (new ListarMovimientosPendientes($this->repo, $this->id))->execute([]);

        $this->assertSame(357000, $resultado['movimientos'][0]['monto']);
    }

    public function test_buscar_facturas_filtra_por_monto(): void
    {
        $resultado = (new BuscarFacturas($this->repo, $this->id))->execute(['monto_min' => 357000, 'monto_max' => 357000]);

        $this->assertSame(['F-2'], array_column($resultado['facturas'], 'numero'));
    }

    public function test_conciliar_une_movimiento_y_factura(): void
    {
        $resultado = (new ConciliarMovimiento($this->repo, $this->id))->execute([
            'movimiento_id' => $this->movimientoId, 'factura_id' => $this->facturaId('F-2'), 'motivo' => 'Mismo cliente y monto',
        ]);

        $this->assertSame(['conciliado' => true, 'factura' => 'F-2'], $resultado);
        $this->assertSame('agente', $this->repo->detalle($this->id)['movimientos'][0]['origen']);
    }

    public function test_conciliar_rechaza_montos_distintos(): void
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Los montos no coinciden');

        (new ConciliarMovimiento($this->repo, $this->id))->execute([
            'movimiento_id' => $this->movimientoId, 'factura_id' => $this->facturaId('F-3'), 'motivo' => 'Pago parcial',
        ]);
    }

    public function test_conciliar_rechaza_un_movimiento_ya_procesado(): void
    {
        $herramienta = new ConciliarMovimiento($this->repo, $this->id);
        $argumentos = ['movimiento_id' => $this->movimientoId, 'factura_id' => $this->facturaId('F-2'), 'motivo' => 'Calza bien'];
        $herramienta->execute($argumentos);

        $this->expectException(ToolException::class);
        $herramienta->execute($argumentos);
    }

    public function test_conciliar_rechaza_movimientos_de_otra_conciliacion(): void
    {
        $otra = $this->repo->crear([], [$this->factura('F-9', 'Otro', 357000)]);

        $this->expectException(ToolException::class);
        (new ConciliarMovimiento($this->repo, $otra))->execute([
            'movimiento_id' => $this->movimientoId, 'factura_id' => 1, 'motivo' => 'Intento cruzado',
        ]);
    }

    public function test_enviar_a_revision_guarda_el_motivo(): void
    {
        (new EnviarARevision($this->repo, $this->id))->execute(['movimiento_id' => $this->movimientoId, 'motivo' => 'Pago parcial']);

        $movimiento = $this->repo->detalle($this->id)['movimientos'][0];
        $this->assertSame('revision', $movimiento['estado']);
        $this->assertSame('Pago parcial', $movimiento['motivo']);
    }

    public function test_enviar_a_revision_rechaza_movimientos_inexistentes(): void
    {
        $this->expectException(ToolException::class);

        (new EnviarARevision($this->repo, $this->id))->execute(['movimiento_id' => 999, 'motivo' => 'No existe']);
    }
}
