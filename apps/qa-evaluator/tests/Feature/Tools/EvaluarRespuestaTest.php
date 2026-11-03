<?php

namespace Tests\Feature\Tools;

use Agentes\Core\Exceptions\ToolException;
use Agentes\Core\Llm\FakeLlmClient;
use App\Agent\AnswerJudge;
use App\Agent\EvaluationContext;
use App\Agent\Tools\EvaluarRespuesta;
use App\Models\Evaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluarRespuestaTest extends TestCase
{
    use RefreshDatabase;

    private const SCORES = '{"similarity_score": 0.9, "quality_score": 0.8, "completeness_score": 0.7}';

    public function test_califica_la_respuesta_y_cuenta_el_intento(): void
    {
        $context = new EvaluationContext(Evaluation::factory()->create());
        $judge = new AnswerJudge(new FakeLlmClient(FakeLlmClient::answer(self::SCORES)));

        $result = (new EvaluarRespuesta($context, $judge))->execute(['respuesta' => 'Una respuesta candidata']);

        $this->assertSame(0.83, $result['overall_score']);
        $this->assertTrue($result['aprobada']);
        $this->assertSame(2, $result['intentos_restantes']);
        $this->assertSame(1, $context->evaluation->fresh()->attempts);
        $this->assertNotNull($context->scoresFor('Una respuesta candidata'));
    }

    public function test_le_muestra_al_juez_la_respuesta_humana(): void
    {
        $evaluation = Evaluation::factory()->create();
        $llm = new FakeLlmClient(FakeLlmClient::answer(self::SCORES));

        (new EvaluarRespuesta(new EvaluationContext($evaluation), new AnswerJudge($llm)))
            ->execute(['respuesta' => 'Una respuesta candidata']);

        $this->assertStringContainsString($evaluation->question->human_answer, $llm->calls[0]['messages'][0]['content']);
    }

    public function test_rechaza_evaluar_cuando_se_acaban_los_intentos(): void
    {
        $context = new EvaluationContext(Evaluation::factory()->create(), maxJudgeAttempts: 1);
        $tool = new EvaluarRespuesta($context, new AnswerJudge(new FakeLlmClient(FakeLlmClient::answer(self::SCORES))));
        $tool->execute(['respuesta' => 'Primera respuesta candidata']);

        $this->expectException(ToolException::class);
        $tool->execute(['respuesta' => 'Segunda respuesta candidata']);
    }
}
