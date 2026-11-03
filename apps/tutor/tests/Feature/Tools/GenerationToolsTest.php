<?php

namespace Tests\Feature\Tools;

use Agentes\Core\Exceptions\ToolException;
use App\Models\Student;
use App\Tutor\GenerationContext;
use App\Tutor\Tools\ConsultarProgreso;
use App\Tutor\Tools\RegistrarEjercicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerationToolsTest extends TestCase
{
    use RefreshDatabase;

    private GenerationContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $student = Student::create(['name' => 'Ana']);
        $student->progressFor('fracciones')->update(['level' => 2, 'correct_streak' => 1]);
        $this->context = new GenerationContext($student, 'fracciones');
    }

    private function registrar(int $nivel = 2): array
    {
        return (new RegistrarEjercicio($this->context))->execute([
            'nivel' => $nivel, 'enunciado' => '¿Cuánto es 1/4 + 2/4?', 'respuesta_esperada' => '3/4',
        ]);
    }

    public function test_consultar_progreso_devuelve_nivel_rachas_y_guia(): void
    {
        $resultado = (new ConsultarProgreso($this->context))->execute([]);

        $this->assertSame(2, $resultado['nivel']);
        $this->assertSame(1, $resultado['racha_correctas']);
        $this->assertStringContainsString('Nivel 1', $resultado['guia_niveles']);
        $this->assertSame([], $resultado['ejercicios_recientes']);
    }

    public function test_consultar_progreso_muestra_los_ejercicios_recientes_para_no_repetirlos(): void
    {
        $this->registrar();

        $resultado = (new ConsultarProgreso(new GenerationContext($this->context->student, 'fracciones')))->execute([]);

        $this->assertSame(['¿Cuánto es 1/4 + 2/4?'], $resultado['ejercicios_recientes']);
    }

    public function test_registrar_guarda_el_ejercicio_del_nivel_actual(): void
    {
        $resultado = $this->registrar();

        $this->assertSame($this->context->exercise->id, $resultado['ejercicio_id']);
        $this->assertSame('3/4', $this->context->exercise->expected_answer);
        $this->assertSame(2, $this->context->exercise->level);
    }

    public function test_registrar_rechaza_un_nivel_distinto_al_del_estudiante(): void
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('está en nivel 2');

        $this->registrar(nivel: 4);
    }

    public function test_registrar_solo_se_puede_usar_una_vez(): void
    {
        $this->registrar();

        $this->expectException(ToolException::class);
        $this->registrar();
    }
}
