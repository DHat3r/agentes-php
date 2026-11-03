<?php

namespace Tests\Feature\Tools;

use Agentes\Core\Exceptions\ToolException;
use App\Models\Exercise;
use App\Models\Student;
use App\Tutor\GradingContext;
use App\Tutor\LevelPolicy;
use App\Tutor\Tools\AjustarNivel;
use App\Tutor\Tools\Corregir;
use App\Tutor\Tools\ObtenerEjercicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingToolsTest extends TestCase
{
    use RefreshDatabase;

    private GradingContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $student = Student::create(['name' => 'Ana']);
        $student->progressFor('fracciones')->update(['level' => 2, 'correct_streak' => 2]);
        $exercise = $student->exercises()->create([
            'topic' => 'fracciones', 'level' => 2, 'statement' => '¿1/4 + 2/4?',
            'expected_answer' => '3/4', 'student_answer' => '0,75',
        ]);
        $this->context = new GradingContext($exercise);
    }

    private function corregir(bool $correcta = true): void
    {
        (new Corregir($this->context))->execute(['correcta' => $correcta, 'retroalimentacion' => '¡Bien! 0,75 equivale a 3/4.']);
    }

    private function ajustar(): array
    {
        return (new AjustarNivel($this->context, new LevelPolicy))->execute([]);
    }

    public function test_obtener_ejercicio_incluye_ambas_respuestas(): void
    {
        $resultado = (new ObtenerEjercicio($this->context))->execute([]);

        $this->assertSame('3/4', $resultado['respuesta_esperada']);
        $this->assertSame('0,75', $resultado['respuesta_estudiante']);
    }

    public function test_corregir_guarda_el_resultado_y_la_retroalimentacion(): void
    {
        $this->corregir();

        $exercise = Exercise::first();
        $this->assertTrue($exercise->correct);
        $this->assertNotNull($exercise->answered_at);
        $this->assertStringContainsString('equivale', $exercise->feedback);
    }

    public function test_corregir_solo_una_vez(): void
    {
        $this->corregir();

        $this->expectException(ToolException::class);
        $this->corregir(false);
    }

    public function test_ajustar_nivel_aplica_la_regla_tras_corregir(): void
    {
        $this->corregir();

        $this->assertSame(['nivel_anterior' => 2, 'nivel_nuevo' => 3], $this->ajustar()); // tercera correcta seguida
        $this->assertTrue(Exercise::first()->level_adjusted);
    }

    public function test_ajustar_nivel_exige_corregir_antes(): void
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Primero corrige');

        $this->ajustar();
    }

    public function test_ajustar_nivel_no_se_aplica_dos_veces(): void
    {
        $this->corregir();
        $this->ajustar();

        $this->expectException(ToolException::class);
        $this->ajustar();
    }
}
