<?php

namespace Tests\Feature;

use App\Models\Progress;
use App\Models\Student;
use App\Tutor\LevelPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function progress(int $level = 1): Progress
    {
        $progress = Student::create(['name' => 'Ana'])->progressFor('fracciones');
        $progress->update(['level' => $level]);

        return $progress;
    }

    /** @param list<bool> $answers */
    private function answer(Progress $progress, array $answers): Progress
    {
        foreach ($answers as $correct) {
            (new LevelPolicy)->apply($progress, $correct);
        }

        return $progress->fresh();
    }

    public function test_sube_de_nivel_con_tres_correctas_seguidas(): void
    {
        $progress = $this->answer($this->progress(), [true, true, true]);

        $this->assertSame(2, $progress->level);
        $this->assertSame(0, $progress->correct_streak);
    }

    public function test_una_incorrecta_reinicia_la_racha_de_correctas(): void
    {
        $this->assertSame(1, $this->answer($this->progress(), [true, true, false, true])->level);
    }

    public function test_baja_de_nivel_con_dos_incorrectas_seguidas(): void
    {
        $this->assertSame(2, $this->answer($this->progress(3), [false, false])->level);
    }

    public function test_no_baja_del_nivel_1_ni_sube_del_5(): void
    {
        $this->assertSame(1, $this->answer($this->progress(1), [false, false, false, false])->level);
        $this->assertSame(5, $this->answer($this->progress(5), [true, true, true])->level);
    }

    public function test_un_estudiante_nuevo_parte_en_nivel_1(): void
    {
        $this->assertSame(1, Student::create(['name' => 'Beto'])->progressFor('ecuaciones')->fresh()->level);
    }
}
