<?php

namespace Tests\Feature\Api;

use Agentes\Core\Exceptions\LlmException;
use Agentes\Core\Llm\FakeLlmClient;
use Agentes\Core\Llm\LlmClient;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutorApiTest extends TestCase
{
    use RefreshDatabase;

    private FakeLlmClient $llm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(LlmClient::class, $this->llm = new FakeLlmClient);
    }

    private function exerciseFor(Student $student): int
    {
        $this->llm->push(
            FakeLlmClient::toolCall('registrar_ejercicio', ['nivel' => 1, 'enunciado' => '¿Cuánto es el 50 % de 80?', 'respuesta_esperada' => '40']),
            FakeLlmClient::answer('¡Ánimo!'),
        );

        return $this->postJson("/api/students/{$student->id}/exercises", ['topic' => 'porcentajes'])->json('id');
    }

    public function test_crea_y_lista_estudiantes(): void
    {
        $this->postJson('/api/students', ['name' => 'Ana'])->assertCreated()->assertJson(['name' => 'Ana']);

        $this->getJson('/api/students')->assertOk()->assertJsonPath('0.name', 'Ana');
        $this->postJson('/api/students', [])->assertUnprocessable();
    }

    public function test_lista_los_temas(): void
    {
        $this->getJson('/api/topics')->assertExactJson(['fracciones', 'porcentajes', 'ecuaciones']);
    }

    public function test_el_progreso_incluye_todos_los_temas(): void
    {
        $student = Student::create(['name' => 'Ana']);
        $student->progressFor('ecuaciones')->update(['level' => 3]);

        $this->getJson("/api/students/{$student->id}/progress")
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('2.topic', 'ecuaciones')
            ->assertJsonPath('2.level', 3)
            ->assertJsonPath('0.level', 1);
    }

    public function test_genera_un_ejercicio_sin_revelar_la_respuesta(): void
    {
        $student = Student::create(['name' => 'Ana']);
        $this->llm->push(
            FakeLlmClient::toolCall('registrar_ejercicio', ['nivel' => 1, 'enunciado' => '¿Cuánto es el 50 % de 80?', 'respuesta_esperada' => '40']),
            FakeLlmClient::answer('¡Ánimo!'),
        );

        $response = $this->postJson("/api/students/{$student->id}/exercises", ['topic' => 'porcentajes'])
            ->assertCreated()
            ->assertJson(['topic' => 'porcentajes', 'level' => 1, 'statement' => '¿Cuánto es el 50 % de 80?']);

        $this->assertStringNotContainsString('expected_answer', $response->getContent());
    }

    public function test_corrige_una_respuesta_y_devuelve_el_nivel(): void
    {
        $id = $this->exerciseFor(Student::create(['name' => 'Ana']));
        $this->llm->push(
            FakeLlmClient::toolCall('corregir', ['correcta' => true, 'retroalimentacion' => '¡Exacto! La mitad de 80 es 40.']),
            FakeLlmClient::toolCall('ajustar_nivel', []),
            FakeLlmClient::answer('Listo.'),
        );

        $this->postJson("/api/exercises/{$id}/answer", ['answer' => '40'])
            ->assertOk()
            ->assertJson(['correct' => true, 'new_level' => 1])
            ->assertJsonPath('trace.0.tool', 'corregir');
    }

    public function test_no_permite_responder_dos_veces(): void
    {
        $id = $this->exerciseFor(Student::create(['name' => 'Ana']));
        $this->llm->push(
            FakeLlmClient::toolCall('corregir', ['correcta' => true, 'retroalimentacion' => '¡Exacto! La mitad de 80 es 40.']),
            FakeLlmClient::answer('Listo.'),
        );
        $this->postJson("/api/exercises/{$id}/answer", ['answer' => '40'])->assertOk();

        $this->postJson("/api/exercises/{$id}/answer", ['answer' => '40'])->assertStatus(409);
    }

    public function test_valida_el_tema(): void
    {
        $student = Student::create(['name' => 'Ana']);

        $this->postJson("/api/students/{$student->id}/exercises", ['topic' => 'química'])->assertUnprocessable();
    }

    public function test_responde_503_si_el_modelo_no_esta_disponible(): void
    {
        $this->llm->push(new LlmException('HTTP 429'));
        $student = Student::create(['name' => 'Ana']);

        $this->postJson("/api/students/{$student->id}/exercises", ['topic' => 'fracciones'])
            ->assertStatus(503)
            ->assertJsonPath('detail', 'HTTP 429');
    }

    public function test_responde_502_si_el_agente_no_completa_la_tarea(): void
    {
        $this->llm->push(FakeLlmClient::answer('No quiero.'));
        $student = Student::create(['name' => 'Ana']);

        $this->postJson("/api/students/{$student->id}/exercises", ['topic' => 'fracciones'])->assertStatus(502);
    }
}
