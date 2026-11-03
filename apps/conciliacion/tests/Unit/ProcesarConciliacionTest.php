<?php

declare(strict_types=1);

namespace Conciliacion\Tests\Unit;

use Agentes\Core\Exceptions\LlmException;
use Agentes\Core\Llm\FakeLlmClient;
use Conciliacion\Agente\AgenteConciliador;
use Conciliacion\Dominio\CsvInvalido;
use Conciliacion\Dominio\LectorCsv;
use Conciliacion\Dominio\ReglaReferenciaExacta;
use Conciliacion\ProcesarConciliacion;
use Conciliacion\Tests\Support\ConBaseDeDatos;
use PHPUnit\Framework\TestCase;

final class ProcesarConciliacionTest extends TestCase
{
    use ConBaseDeDatos;

    private const CARTOLA = "fecha,descripcion,monto\n"
        ."2026-10-01,TRANSF FACT F-1,1000\n"       // movimiento 1: lo resuelve la regla
        ."2026-10-02,ABONO PANADERIA,2000\n"       // movimiento 2: lo resuelve el agente
        ."2026-10-03,DEPOSITO SIN GLOSA,999\n";    // movimiento 3: revisión

    private const FACTURAS = "numero,cliente,monto\nF-1,Ana,1000\nF-2,Panadería,2000\n";

    protected function setUp(): void
    {
        $this->prepararBaseDeDatos();
    }

    private function procesar(FakeLlmClient $llm): array
    {
        $caso = new ProcesarConciliacion(new LectorCsv, $this->repo, new ReglaReferenciaExacta($this->repo), new AgenteConciliador($llm, $this->repo));
        $id = $caso(self::CARTOLA, self::FACTURAS);

        return $this->repo->detalle($id);
    }

    public function test_combina_regla_agente_y_revision(): void
    {
        $llm = new FakeLlmClient(
            FakeLlmClient::toolCall('listar_movimientos_pendientes', []),
            FakeLlmClient::toolCall('buscar_facturas', ['monto_min' => 2000, 'monto_max' => 2000]),
            FakeLlmClient::toolCall('conciliar_movimiento', ['movimiento_id' => 2, 'factura_id' => 2, 'motivo' => 'Panadería, mismo monto']),
            FakeLlmClient::toolCall('enviar_a_revision', ['movimiento_id' => 3, 'motivo' => 'No hay factura de 999']),
            FakeLlmClient::answer('Concilié 1 y envié 1 a revisión.'),
        );

        $detalle = $this->procesar($llm);

        $this->assertSame(['regla', 'agente', 'agente'], array_column($detalle['movimientos'], 'origen'));
        $this->assertSame(['conciliado', 'conciliado', 'revision'], array_column($detalle['movimientos'], 'estado'));
        $this->assertSame('final', end($detalle['trace'])['type']);
        $this->assertNull($detalle['error']);
    }

    public function test_el_agente_solo_ve_lo_que_la_regla_no_resolvio(): void
    {
        $llm = new FakeLlmClient(FakeLlmClient::toolCall('listar_movimientos_pendientes', []), FakeLlmClient::answer('Nada que hacer.'));

        $this->procesar($llm);

        $pendientes = json_decode($llm->calls[1]['messages'][3]['content'], true)['movimientos'];
        $this->assertSame([2, 3], array_column($pendientes, 'id'));
    }

    public function test_lo_que_el_agente_no_resuelve_pasa_a_revision(): void
    {
        $detalle = $this->procesar(new FakeLlmClient(FakeLlmClient::answer('Listo.')));

        $this->assertSame(['conciliado', 'revision', 'revision'], array_column($detalle['movimientos'], 'estado'));
        $this->assertSame('sistema', $detalle['movimientos'][1]['origen']);
    }

    public function test_si_el_modelo_falla_guarda_el_error_y_no_pierde_lo_resuelto(): void
    {
        $detalle = $this->procesar(new FakeLlmClient(new LlmException('HTTP 429')));

        $this->assertSame('conciliado', $detalle['movimientos'][0]['estado']);
        $this->assertStringContainsString('HTTP 429', $detalle['movimientos'][1]['motivo']);
        $this->assertSame('HTTP 429', $detalle['error']);
    }

    public function test_no_llama_al_modelo_si_la_regla_resuelve_todo(): void
    {
        $llm = new FakeLlmClient;
        $caso = new ProcesarConciliacion(new LectorCsv, $this->repo, new ReglaReferenciaExacta($this->repo), new AgenteConciliador($llm, $this->repo));

        $caso("fecha,descripcion,monto\n2026-10-01,PAGO F-1,1000\n", self::FACTURAS);

        $this->assertSame([], $llm->calls);
    }

    public function test_un_csv_invalido_no_deja_una_conciliacion_a_medias(): void
    {
        $caso = new ProcesarConciliacion(new LectorCsv, $this->repo, new ReglaReferenciaExacta($this->repo), new AgenteConciliador(new FakeLlmClient, $this->repo));

        try {
            $caso(self::CARTOLA, "numero,cliente\nF-1,Ana");
            $this->fail('Se esperaba CsvInvalido');
        } catch (CsvInvalido) {
            $this->assertSame([], $this->repo->listar());
        }
    }
}
