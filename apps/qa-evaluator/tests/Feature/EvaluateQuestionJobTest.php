<?php

namespace Tests\Feature;

use Agentes\Core\Exceptions\LlmException;
use Agentes\Core\Llm\FakeLlmClient;
use Agentes\Core\Llm\LlmClient;
use App\Agent\AnswerJudge;
use App\Jobs\EvaluateQuestion;
use App\Models\Evaluation;
use App\Support\EvaluationCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluateQuestionJobTest extends TestCase
{
    use RefreshDatabase;

    private function fakeModels(FakeLlmClient $agent, ?FakeLlmClient $judge = null): void
    {
        $this->app->instance(LlmClient::class, $agent);
        $this->app->instance(AnswerJudge::class, new AnswerJudge($judge ?? new FakeLlmClient(
            FakeLlmClient::answer('{"similarity_score": 0.8, "quality_score": 0.8, "completeness_score": 0.8}'),
        )));
    }

    private function handle(Evaluation $evaluation): void
    {
        app()->call([new EvaluateQuestion($evaluation), 'handle']);
    }

    public function test_completa_la_evaluacion_guarda_la_traza_y_la_cachea(): void
    {
        $evaluation = Evaluation::factory()->create();
        $this->fakeModels(new FakeLlmClient(
            FakeLlmClient::toolCall('evaluar_respuesta', ['respuesta' => 'Respuesta del agente']),
            FakeLlmClient::toolCall('guardar_resultado', ['respuesta' => 'Respuesta del agente']),
            FakeLlmClient::answer('Listo.'),
        ));

        $this->handle($evaluation);

        $evaluation->refresh();
        $this->assertSame(Evaluation::COMPLETED, $evaluation->status);
        $this->assertSame('final', collect($evaluation->trace)->last()['type']);
        $this->assertSame($evaluation->id, app(EvaluationCache::class)->lookup($evaluation->question_id)?->id);
    }

    public function test_marca_fallida_si_el_agente_termina_sin_guardar(): void
    {
        $evaluation = Evaluation::factory()->create();
        $this->fakeModels(new FakeLlmClient(FakeLlmClient::answer('No quise guardar.')));

        $this->handle($evaluation);

        $evaluation->refresh();
        $this->assertSame(Evaluation::FAILED, $evaluation->status);
        $this->assertStringContainsString('sin guardar', $evaluation->error);
        $this->assertNotEmpty($evaluation->trace);
    }

    public function test_marca_fallida_si_supera_el_limite_de_iteraciones(): void
    {
        config(['services.openrouter.max_iterations' => 1]);
        $evaluation = Evaluation::factory()->create();
        $this->fakeModels(new FakeLlmClient(FakeLlmClient::toolCall('buscar_pregunta', ['pregunta_id' => 1])));

        $this->handle($evaluation);

        $this->assertSame(Evaluation::FAILED, $evaluation->fresh()->status);
    }

    public function test_deja_escapar_los_errores_del_modelo_para_que_la_cola_reintente(): void
    {
        $evaluation = Evaluation::factory()->create();
        $this->fakeModels(new FakeLlmClient(new LlmException('timeout')));

        $this->expectException(LlmException::class);
        $this->handle($evaluation);
    }

    public function test_al_agotar_los_reintentos_marca_fallida_con_el_error(): void
    {
        $evaluation = Evaluation::factory()->create();

        (new EvaluateQuestion($evaluation))->failed(new LlmException('OpenRouter caído'));

        $this->assertSame('OpenRouter caído', $evaluation->fresh()->error);
        $this->assertSame(Evaluation::FAILED, $evaluation->fresh()->status);
    }

    public function test_no_repite_una_evaluacion_ya_completada(): void
    {
        $evaluation = Evaluation::factory()->completed()->create();
        $this->fakeModels($llm = new FakeLlmClient);

        $this->handle($evaluation);

        $this->assertSame([], $llm->calls);
    }

    public function test_esta_configurado_con_tres_intentos_y_backoff(): void
    {
        $job = new EvaluateQuestion(Evaluation::factory()->create());

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff);
    }
}
