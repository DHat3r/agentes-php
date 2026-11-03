<?php

declare(strict_types=1);

namespace Conciliacion\Tests\Unit;

use Conciliacion\Dominio\CsvInvalido;
use Conciliacion\Dominio\LectorCsv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LectorCsvTest extends TestCase
{
    public function test_lee_la_cartola_y_descarta_los_cargos(): void
    {
        $csv = "fecha,descripcion,monto\n2026-10-01,TRANSF F-1001,1190000\n2026-10-02,PAGO LUZ,-45000\n";

        $this->assertSame(
            [['fecha' => '2026-10-01', 'descripcion' => 'TRANSF F-1001', 'monto' => 1190000]],
            (new LectorCsv)->cartola($csv),
        );
    }

    public function test_acepta_punto_y_coma_bom_y_montos_con_puntos(): void
    {
        $csv = "\xEF\xBB\xBFFecha;Descripcion;Monto\r\n2026-10-01;ABONO;\"$1.190.000\"\r\n";

        $this->assertSame(1190000, (new LectorCsv)->cartola($csv)[0]['monto']);
    }

    public function test_lee_facturas_con_fecha_opcional(): void
    {
        $facturas = (new LectorCsv)->facturas("numero,cliente,monto\nF-1,\"Comercial Andes, Ltda\",1000\n");

        $this->assertSame(['numero' => 'F-1', 'cliente' => 'Comercial Andes, Ltda', 'monto' => 1000, 'fecha_emision' => null], $facturas[0]);
    }

    public static function archivosInvalidos(): array
    {
        return [
            'vacío' => ['', 'vacío'],
            'sin columna monto' => ["fecha,descripcion\n2026-10-01,x", 'faltan columnas: monto'],
            'monto con letras' => ["fecha,descripcion,monto\n2026-10-01,x,mil", "Monto inválido 'mil' en la línea 2"],
            'sin descripción' => ["fecha,descripcion,monto\n2026-10-01,,100", "Falta 'descripcion' en la línea 2"],
        ];
    }

    #[DataProvider('archivosInvalidos')]
    public function test_rechaza_cartolas_invalidas_con_un_mensaje_claro(string $csv, string $mensaje): void
    {
        $this->expectException(CsvInvalido::class);
        $this->expectExceptionMessage($mensaje);

        (new LectorCsv)->cartola($csv);
    }
}
