<?php

declare(strict_types=1);

namespace Conciliacion\Tests\Unit;

use Conciliacion\Dominio\ReglaReferenciaExacta;
use Conciliacion\Tests\Support\ConBaseDeDatos;
use PHPUnit\Framework\TestCase;

final class ReglaReferenciaExactaTest extends TestCase
{
    use ConBaseDeDatos;

    protected function setUp(): void
    {
        $this->prepararBaseDeDatos();
    }

    private function estados(int $id): array
    {
        return array_column($this->repo->detalle($id)['movimientos'], 'estado', 'descripcion');
    }

    public function test_concilia_cuando_la_glosa_menciona_la_factura_y_el_monto_coincide(): void
    {
        $id = $this->repo->crear(
            [$this->movimiento('TRANSF COMERCIAL ANDES FACT F-1001', 1190000)],
            [$this->factura('F-1001', 'Comercial Andes', 1190000)],
        );

        $this->assertSame(1, (new ReglaReferenciaExacta($this->repo))->aplicar($id));
        $movimiento = $this->repo->detalle($id)['movimientos'][0];
        $this->assertSame('conciliado', $movimiento['estado']);
        $this->assertSame('regla', $movimiento['origen']);
    }

    public function test_no_concilia_si_el_monto_es_distinto(): void
    {
        $id = $this->repo->crear([$this->movimiento('PAGO F-1001', 500)], [$this->factura('F-1001', 'Ana', 1000)]);

        (new ReglaReferenciaExacta($this->repo))->aplicar($id);

        $this->assertSame(['PAGO F-1001' => 'pendiente'], $this->estados($id));
    }

    public function test_no_confunde_numeros_que_empiezan_igual(): void
    {
        $id = $this->repo->crear([$this->movimiento('PAGO F-10011', 1000)], [$this->factura('F-1001', 'Ana', 1000)]);

        (new ReglaReferenciaExacta($this->repo))->aplicar($id);

        $this->assertSame(['PAGO F-10011' => 'pendiente'], $this->estados($id));
    }

    public function test_no_usa_dos_veces_la_misma_factura(): void
    {
        $id = $this->repo->crear(
            [$this->movimiento('PAGO F-1', 1000), $this->movimiento('OTRO PAGO F-1', 1000)],
            [$this->factura('F-1', 'Ana', 1000)],
        );

        $this->assertSame(1, (new ReglaReferenciaExacta($this->repo))->aplicar($id));
        $this->assertSame(['PAGO F-1' => 'conciliado', 'OTRO PAGO F-1' => 'pendiente'], $this->estados($id));
    }
}
