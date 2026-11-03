<?php

namespace Tests\Feature\Tools;

use Agentes\Core\Exceptions\ToolException;
use App\Agent\EvaluationContext;
use App\Agent\JudgeScores;
use App\Agent\Tools\GuardarResultado;
use App\Models\Evaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardarResultadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarda_la_respuesta_con_los_puntajes_del_juez(): void
    {
        $context = new EvaluationContext(Evaluation::factory()->create());
        $context->remember('Respuesta evaluada', new JudgeScores(1.0, 0.5, 0.5));

        $result = (new GuardarResultado($context))->execute(['respuesta' => "  Respuesta\n evaluada "]);

        $evaluation = $context->evaluation->fresh();
        $this->assertTrue($result['guardado']);
        $this->assertSame(Evaluation::COMPLETED, $evaluation->status);
        $this->assertSame(0.75, $evaluation->overall_score);
        $this->assertNotNull($evaluation->completed_at);
    }

    public function test_rechaza_respuestas_que_no_paso_por_el_juez(): void
    {
        $context = new EvaluationContext(Evaluation::factory()->create());

        try {
            (new GuardarResultado($context))->execute(['respuesta' => 'Respuesta inventada']);
            $this->fail('Se esperaba ToolException');
        } catch (ToolException) {
            $this->assertSame(Evaluation::QUEUED, $context->evaluation->fresh()->status);
        }
    }
}
