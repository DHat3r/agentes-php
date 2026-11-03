<?php

namespace Tests\Feature\Tools;

use Agentes\Core\Exceptions\ToolException;
use App\Agent\Tools\BuscarPregunta;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuscarPreguntaTest extends TestCase
{
    use RefreshDatabase;

    public function test_devuelve_la_pregunta_sin_la_respuesta_humana(): void
    {
        $question = Question::factory()->create(['question_text' => '¿Qué es PHP?', 'human_answer' => 'secreta']);

        $result = (new BuscarPregunta)->execute(['pregunta_id' => $question->id]);

        $this->assertSame('¿Qué es PHP?', $result['pregunta']);
        $this->assertStringNotContainsString('secreta', json_encode($result));
    }

    public function test_avisa_si_la_pregunta_no_existe(): void
    {
        $this->expectException(ToolException::class);

        (new BuscarPregunta)->execute(['pregunta_id' => 999]);
    }
}
