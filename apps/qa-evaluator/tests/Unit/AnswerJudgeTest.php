<?php

namespace Tests\Unit;

use Agentes\Core\Exceptions\LlmException;
use Agentes\Core\Llm\FakeLlmClient;
use App\Agent\AnswerJudge;
use PHPUnit\Framework\TestCase;

class AnswerJudgeTest extends TestCase
{
    public function test_calcula_el_puntaje_general_con_los_pesos_originales(): void
    {
        $judge = new AnswerJudge(new FakeLlmClient(
            FakeLlmClient::answer('{"similarity_score": 0.8, "quality_score": 0.6, "completeness_score": 0.5}'),
        ));

        $scores = $judge->judge('¿Pregunta?', 'Humana', 'Candidata');

        $this->assertSame(0.68, $scores->overall); // 0.8*0.5 + 0.6*0.3 + 0.5*0.2
        $this->assertFalse($scores->approved());
    }

    public function test_extrae_el_json_aunque_venga_envuelto_en_texto(): void
    {
        $judge = new AnswerJudge(new FakeLlmClient(FakeLlmClient::answer(
            "Claro, aquí va:\n```json\n{\"similarity_score\": 1, \"quality_score\": 0.9, \"completeness_score\": 0.9}\n```",
        )));

        $this->assertTrue($judge->judge('p', 'h', 'c')->approved());
    }

    public function test_limita_los_puntajes_entre_0_y_1(): void
    {
        $judge = new AnswerJudge(new FakeLlmClient(
            FakeLlmClient::answer('{"similarity_score": 7, "quality_score": -2, "completeness_score": 0.5}'),
        ));

        $scores = $judge->judge('p', 'h', 'c');

        $this->assertSame(1.0, $scores->similarity);
        $this->assertSame(0.0, $scores->quality);
    }

    public function test_envia_la_pregunta_y_ambas_respuestas_al_juez(): void
    {
        $llm = new FakeLlmClient(FakeLlmClient::answer('{"similarity_score": 1, "quality_score": 1, "completeness_score": 1}'));

        (new AnswerJudge($llm))->judge('¿Qué es PHP?', 'Un lenguaje', 'Un lenguaje de servidor');

        $prompt = $llm->calls[0]['messages'][0]['content'];
        $this->assertStringContainsString('¿Qué es PHP?', $prompt);
        $this->assertStringContainsString('Un lenguaje de servidor', $prompt);
        $this->assertSame([], $llm->calls[0]['tools']);
    }

    public function test_falla_si_el_juez_no_devuelve_json(): void
    {
        $this->expectException(LlmException::class);

        (new AnswerJudge(new FakeLlmClient(FakeLlmClient::answer('No sé'))))->judge('p', 'h', 'c');
    }

    public function test_falla_si_falta_un_puntaje(): void
    {
        $this->expectException(LlmException::class);

        (new AnswerJudge(new FakeLlmClient(FakeLlmClient::answer('{"similarity_score": 1}'))))->judge('p', 'h', 'c');
    }
}
