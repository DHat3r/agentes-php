<?php

namespace Tests\Feature;

use Agentes\Core\Exceptions\MaxIterationsReached;
use Agentes\Core\Llm\FakeLlmClient;
use Agentes\Core\Llm\LlmClient;
use App\Agent\AnswerJudge;
use App\Agent\QaAgent;
use App\Models\Evaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba el agente completo con dos modelos simulados: el agente y el juez.
 */
class QaAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_mejora_la_respuesta_hasta_aprobar_y_guarda_la_mejor(): void
    {
        $evaluation = Evaluation::factory()->create();
        $id = $evaluation->question_id;

        $this->app->instance(LlmClient::class, new FakeLlmClient(
            FakeLlmClient::toolCall('buscar_pregunta', ['pregunta_id' => $id]),
            FakeLlmClient::toolCall('evaluar_respuesta', ['respuesta' => 'Primera respuesta corta']),
            FakeLlmClient::toolCall('evaluar_respuesta', ['respuesta' => 'Segunda respuesta, más completa']),
            FakeLlmClient::toolCall('guardar_resultado', ['respuesta' => 'Segunda respuesta, más completa']),
            FakeLlmClient::answer('Guardé la segunda respuesta con 0.9.'),
        ));
        $this->app->instance(AnswerJudge::class, new AnswerJudge(new FakeLlmClient(
            FakeLlmClient::answer('{"similarity_score": 0.5, "quality_score": 0.5, "completeness_score": 0.5}'),
            FakeLlmClient::answer('{"similarity_score": 0.9, "quality_score": 0.9, "completeness_score": 0.9}'),
        )));

        $result = app(QaAgent::class)->evaluate($evaluation);

        $evaluation->refresh();
        $this->assertSame(Evaluation::COMPLETED, $evaluation->status);
        $this->assertSame('Segunda respuesta, más completa', $evaluation->llm_answer);
        $this->assertSame(0.9, $evaluation->overall_score);
        $this->assertSame(2, $evaluation->attempts);
        $this->assertSame(2, $result->toolCallCount('evaluar_respuesta'));
        $this->assertSame(5, $result->iterations);
    }

    public function test_usa_el_limite_de_iteraciones_de_la_configuracion(): void
    {
        config(['services.openrouter.max_iterations' => 1]);
        $this->app->instance(LlmClient::class, new FakeLlmClient(
            FakeLlmClient::toolCall('buscar_pregunta', ['pregunta_id' => 1]),
        ));
        $this->app->instance(AnswerJudge::class, new AnswerJudge(new FakeLlmClient));

        $this->expectException(MaxIterationsReached::class);

        app(QaAgent::class)->evaluate(Evaluation::factory()->create());
    }
}
