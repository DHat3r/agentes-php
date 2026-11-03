<?php

namespace Tests\Feature\Tools;

use App\Agent\Tools\BuscarRespuestasPrevias;
use App\Models\Evaluation;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuscarRespuestasPreviasTest extends TestCase
{
    use RefreshDatabase;

    private function evaluated(string $question, float $overall, string $status = Evaluation::COMPLETED): Evaluation
    {
        return Evaluation::factory()
            ->for(Question::factory()->state(['question_text' => $question]))
            ->completed($overall)
            ->create(['status' => $status]);
    }

    public function test_devuelve_solo_respuestas_aprobadas_de_preguntas_parecidas(): void
    {
        $this->evaluated('¿Cómo funciona la memoria RAM?', 0.9);
        $this->evaluated('¿Qué memoria conviene comprar?', 0.5);
        $this->evaluated('¿Quién pintó la Mona Lisa?', 0.95);

        $result = (new BuscarRespuestasPrevias(excludeQuestionId: 0))->execute(['texto' => 'memoria del computador']);

        $this->assertCount(1, $result['resultados']);
        $this->assertSame('¿Cómo funciona la memoria RAM?', $result['resultados'][0]['pregunta']);
    }

    public function test_excluye_la_pregunta_que_se_esta_evaluando(): void
    {
        $evaluation = $this->evaluated('¿Cómo funciona la memoria RAM?', 0.9);

        $result = (new BuscarRespuestasPrevias($evaluation->question_id))->execute(['texto' => 'memoria']);

        $this->assertSame([], $result['resultados']);
    }

    public function test_ignora_evaluaciones_no_completadas(): void
    {
        $this->evaluated('¿Cómo funciona la memoria RAM?', 0.9, Evaluation::FAILED);

        $result = (new BuscarRespuestasPrevias(0))->execute(['texto' => 'memoria']);

        $this->assertSame([], $result['resultados']);
    }

    public function test_sin_palabras_clave_no_consulta_nada(): void
    {
        $this->assertSame(['resultados' => []], (new BuscarRespuestasPrevias(0))->execute(['texto' => 'de la']));
    }
}
