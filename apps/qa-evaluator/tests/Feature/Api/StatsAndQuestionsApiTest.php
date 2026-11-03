<?php

namespace Tests\Feature\Api;

use App\Models\Evaluation;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsAndQuestionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_resume_evaluaciones_por_estado_y_promedio(): void
    {
        Evaluation::factory()->completed(0.8)->create();
        Evaluation::factory()->completed(0.6)->create();
        Evaluation::factory()->create(['status' => Evaluation::FAILED]);
        Evaluation::factory()->create(['status' => Evaluation::RUNNING]);

        $this->getJson('/api/stats')->assertOk()->assertExactJson([
            'questions' => 4,
            'evaluations' => ['completed' => 2, 'failed' => 1, 'pending' => 1],
            'average_overall' => 0.7,
            'cache' => ['hits' => 0, 'misses' => 0],
        ]);
    }

    public function test_promedio_nulo_sin_evaluaciones(): void
    {
        $this->getJson('/api/stats')->assertJsonPath('average_overall', null);
    }

    public function test_lista_preguntas_paginadas_de_a_10(): void
    {
        Question::factory()->count(12)->create();

        $this->getJson('/api/questions?page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 2);
    }
}
