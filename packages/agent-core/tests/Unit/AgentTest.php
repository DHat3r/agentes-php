<?php

declare(strict_types=1);

namespace Agentes\Core\Tests\Unit;

use Agentes\Core\Agent;
use Agentes\Core\Exceptions\LlmException;
use Agentes\Core\Exceptions\MaxIterationsReached;
use Agentes\Core\Llm\FakeLlmClient;
use Agentes\Core\Llm\LlmResponse;
use Agentes\Core\Llm\ToolCall;
use Agentes\Core\Tests\Support\SumTool;
use Agentes\Core\Tools\ToolRegistry;
use PHPUnit\Framework\TestCase;

final class AgentTest extends TestCase
{
    private SumTool $tool;

    protected function setUp(): void
    {
        $this->tool = new SumTool;
    }

    private function agent(FakeLlmClient $llm, int $maxIterations = 8): Agent
    {
        return new Agent($llm, new ToolRegistry($this->tool), $maxIterations);
    }

    public function test_devuelve_la_respuesta_cuando_el_modelo_no_pide_herramientas(): void
    {
        $result = $this->agent(new FakeLlmClient(FakeLlmClient::answer('Hola')))->run('sistema', 'hola');

        $this->assertSame('Hola', $result->answer);
        $this->assertSame(1, $result->iterations);
    }

    public function test_ejecuta_la_herramienta_y_le_devuelve_el_resultado_al_modelo(): void
    {
        $llm = new FakeLlmClient(
            FakeLlmClient::toolCall('sumar', ['a' => 2, 'b' => 3]),
            FakeLlmClient::answer('La suma es 5'),
        );

        $result = $this->agent($llm)->run('sistema', '¿2 + 3?');

        $this->assertSame('La suma es 5', $result->answer);
        $this->assertSame(2, $result->iterations);
        $this->assertSame(['resultado' => 5], $llm->lastToolResult());
        $this->assertSame(1, $result->toolCallCount('sumar'));
        $this->assertSame(['tool_call', 'tool_result', 'final'], array_column($result->trace(), 'type'));
    }

    public function test_envia_las_definiciones_de_herramientas_al_modelo(): void
    {
        $llm = new FakeLlmClient(FakeLlmClient::answer('ok'));
        $this->agent($llm)->run('sistema', 'hola');

        $this->assertSame('sumar', $llm->calls[0]['tools'][0]['function']['name']);
        $this->assertSame('system', $llm->calls[0]['messages'][0]['role']);
    }

    public function test_avisa_al_modelo_si_pide_una_herramienta_inexistente(): void
    {
        $llm = new FakeLlmClient(
            FakeLlmClient::toolCall('restar', ['a' => 1]),
            FakeLlmClient::answer('Perdón'),
        );

        $this->agent($llm)->run('sistema', 'hola');

        $this->assertStringContainsString("'restar' no existe", $llm->lastToolResult()['error']);
    }

    public function test_avisa_al_modelo_si_los_argumentos_no_son_json(): void
    {
        $llm = new FakeLlmClient(
            new LlmResponse(null, [new ToolCall('c1', 'sumar', '{a: 1')]),
            FakeLlmClient::answer('Perdón'),
        );

        $this->agent($llm)->run('sistema', 'hola');

        $this->assertStringContainsString('JSON válido', $llm->lastToolResult()['error']);
        $this->assertSame(0, $this->tool->executions);
    }

    public function test_no_ejecuta_la_herramienta_si_los_argumentos_no_cumplen_el_esquema(): void
    {
        $llm = new FakeLlmClient(
            FakeLlmClient::toolCall('sumar', ['a' => 'dos']),
            FakeLlmClient::answer('Perdón'),
        );

        $this->agent($llm)->run('sistema', 'hola');

        $error = $llm->lastToolResult()['error'];
        $this->assertStringContainsString("Falta el argumento obligatorio 'b'", $error);
        $this->assertStringContainsString("'a' debe ser de tipo integer", $error);
        $this->assertSame(0, $this->tool->executions);
    }

    public function test_devuelve_al_modelo_los_errores_de_negocio_de_la_herramienta(): void
    {
        $llm = new FakeLlmClient(
            FakeLlmClient::toolCall('sumar', ['a' => -1, 'b' => 2]),
            FakeLlmClient::toolCall('sumar', ['a' => 1, 'b' => 2]),
            FakeLlmClient::answer('La suma es 3'),
        );

        $result = $this->agent($llm)->run('sistema', 'hola');

        $this->assertSame('La suma es 3', $result->answer);
        $this->assertSame(2, $this->tool->executions);
    }

    public function test_ejecuta_varias_herramientas_pedidas_en_la_misma_respuesta(): void
    {
        $llm = new FakeLlmClient(
            new LlmResponse(null, [
                new ToolCall('c1', 'sumar', '{"a":1,"b":1}'),
                new ToolCall('c2', 'sumar', '{"a":2,"b":2}'),
            ]),
            FakeLlmClient::answer('2 y 4'),
        );

        $this->agent($llm)->run('sistema', 'hola');

        $toolMessages = array_values(array_filter($llm->calls[1]['messages'], fn ($m) => $m['role'] === 'tool'));
        $this->assertSame(['c1', 'c2'], array_column($toolMessages, 'tool_call_id'));
    }

    public function test_corta_el_ciclo_al_llegar_al_limite_de_iteraciones(): void
    {
        $llm = new FakeLlmClient(
            FakeLlmClient::toolCall('sumar', ['a' => 1, 'b' => 1]),
            FakeLlmClient::toolCall('sumar', ['a' => 1, 'b' => 1]),
            FakeLlmClient::toolCall('sumar', ['a' => 1, 'b' => 1]),
        );

        try {
            $this->agent($llm, maxIterations: 2)->run('sistema', 'hola');
            $this->fail('Se esperaba MaxIterationsReached');
        } catch (MaxIterationsReached $e) {
            $this->assertSame(2, $e->maxIterations);
            $this->assertCount(4, $e->steps);
            $this->assertCount(2, $llm->calls);
        }
    }

    public function test_propaga_los_errores_del_modelo_para_que_quien_llama_reintente(): void
    {
        $this->expectException(LlmException::class);

        $this->agent(new FakeLlmClient(new LlmException('timeout')))->run('sistema', 'hola');
    }

    public function test_falla_si_el_modelo_responde_vacio(): void
    {
        $this->expectException(LlmException::class);

        $this->agent(new FakeLlmClient(new LlmResponse('  ')))->run('sistema', 'hola');
    }
}
