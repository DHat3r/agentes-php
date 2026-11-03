<?php

namespace Tests\Feature;

use Agentes\Core\Llm\FakeLlmClient;
use Agentes\Core\Llm\LlmClient;
use App\Models\Student;
use App\Tutor\AgentIncomplete;
use App\Tutor\TutorAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutorAgentTest extends TestCase
{
    use RefreshDatabase;

    private function fake(FakeLlmClient $llm): TutorAgent
    {
        $this->app->instance(LlmClient::class, $llm);

        return app(TutorAgent::class);
    }

    private function exercise(Student $student)
    {
        return $student->exercises()->create([
            'topic' => 'porcentajes', 'level' => 1, 'statement' => '¿Cuánto es el 10 % de $5.000?', 'expected_answer' => '500',
        ]);
    }

    public function test_genera_un_ejercicio_del_nivel_del_estudiante(): void
    {
        $student = Student::create(['name' => 'Ana']);
        $agent = $this->fake(new FakeLlmClient(
            FakeLlmClient::toolCall('consultar_progreso', []),
            FakeLlmClient::toolCall('registrar_ejercicio', ['nivel' => 1, 'enunciado' => '¿Cuánto es 1/5 + 2/5?', 'respuesta_esperada' => '3/5']),
            FakeLlmClient::answer('¡Tú puedes!'),
        ));

        [$exercise, $result] = $agent->generate($student, 'fracciones');

        $this->assertSame('¿Cuánto es 1/5 + 2/5?', $exercise->statement);
        $this->assertSame(3, $result->iterations);
        $this->assertNotEmpty($exercise->fresh()->trace);
    }

    public function test_falla_si_el_modelo_no_registra_el_ejercicio(): void
    {
        $this->expectException(AgentIncomplete::class);

        $this->fake(new FakeLlmClient(FakeLlmClient::answer('Aquí va: 2+2')))->generate(Student::create(['name' => 'Ana']), 'fracciones');
    }

    public function test_corrige_y_ajusta_el_nivel(): void
    {
        $student = Student::create(['name' => 'Ana']);
        $student->progressFor('porcentajes')->update(['correct_streak' => 2]);
        $agent = $this->fake(new FakeLlmClient(
            FakeLlmClient::toolCall('obtener_ejercicio', []),
            FakeLlmClient::toolCall('corregir', ['correcta' => true, 'retroalimentacion' => 'Correcto: 10 % es dividir por 10.']),
            FakeLlmClient::toolCall('ajustar_nivel', []),
            FakeLlmClient::answer('Listo.'),
        ));

        [$exercise, , $newLevel] = $agent->grade($this->exercise($student), ' 500 ');

        $this->assertSame('500', $exercise->student_answer);
        $this->assertTrue($exercise->correct);
        $this->assertSame(2, $newLevel);
    }

    public function test_ajusta_el_nivel_aunque_el_modelo_lo_olvide(): void
    {
        $student = Student::create(['name' => 'Ana']);
        $student->progressFor('porcentajes')->update(['level' => 3, 'wrong_streak' => 1]);
        $exercise = $this->exercise($student);
        $agent = $this->fake(new FakeLlmClient(
            FakeLlmClient::toolCall('corregir', ['correcta' => false, 'retroalimentacion' => 'El 10 % de 5.000 es 500, no 50.']),
            FakeLlmClient::answer('Listo.'),
        ));

        [, , $newLevel] = $agent->grade($exercise, '50');

        $this->assertSame(2, $newLevel);
        $this->assertTrue($exercise->fresh()->level_adjusted);
    }

    public function test_falla_si_el_modelo_no_corrige(): void
    {
        $student = Student::create(['name' => 'Ana']);

        $this->expectException(AgentIncomplete::class);

        $this->fake(new FakeLlmClient(FakeLlmClient::answer('Está bien.')))->grade($this->exercise($student), '500');
    }
}
