<?php

namespace Tests\Feature\Api;

use App\Jobs\EvaluateQuestion;
use App\Models\Evaluation;
use App\Models\Question;
use App\Support\EvaluationCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EvaluationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // El job se prueba aparte; aquí solo importa que se encole.
    }

    public function test_encola_una_evaluacion_y_responde_202(): void
    {
        $question = Question::factory()->create();

        $this->postJson('/api/evaluations', ['question_id' => $question->id])
            ->assertStatus(202)
            ->assertJson(['question_id' => $question->id, 'status' => 'queued', 'cached' => false]);

        Queue::assertPushed(EvaluateQuestion::class, fn ($job) => $job->evaluation->question_id === $question->id);
    }

    public function test_devuelve_la_evaluacion_en_cache_sin_encolar(): void
    {
        $evaluation = Evaluation::factory()->completed(0.9)->create();
        app(EvaluationCache::class)->remember($evaluation);

        $this->postJson('/api/evaluations', ['question_id' => $evaluation->question_id])
            ->assertOk()
            ->assertJson(['id' => $evaluation->id, 'cached' => true, 'overall_score' => 0.9]);

        Queue::assertNothingPushed();
        $this->assertSame(['hits' => 1, 'misses' => 0], app(EvaluationCache::class)->stats());
    }

    public function test_force_ignora_la_cache(): void
    {
        $evaluation = Evaluation::factory()->completed()->create();
        app(EvaluationCache::class)->remember($evaluation);

        $this->postJson('/api/evaluations', ['question_id' => $evaluation->question_id, 'force' => true])
            ->assertStatus(202);

        Queue::assertPushed(EvaluateQuestion::class);
    }

    public function test_valida_que_la_pregunta_exista(): void
    {
        $this->postJson('/api/evaluations', ['question_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question_id');
    }

    public function test_muestra_una_evaluacion_con_su_traza(): void
    {
        $evaluation = Evaluation::factory()->completed()->create([
            'trace' => [['type' => 'final', 'tool' => null, 'arguments' => [], 'content' => 'Listo']],
        ]);

        $this->getJson("/api/evaluations/{$evaluation->id}")
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('trace.0.content', 'Listo');
    }

    public function test_lista_las_evaluaciones_paginadas(): void
    {
        Evaluation::factory()->count(3)->create();

        $this->getJson('/api/evaluations')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_responde_404_si_la_evaluacion_no_existe(): void
    {
        $this->getJson('/api/evaluations/999')->assertNotFound();
    }
}
