<?php

namespace Tests\Feature\Api;

use App\Jobs\EvaluateQuestion;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TrafficApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_encola_una_evaluacion_por_cada_pregunta_elegida(): void
    {
        Queue::fake();
        $ids = Question::factory()->count(5)->create()->pluck('id')->all();

        $response = $this->postJson('/api/traffic', ['count' => 8, 'distribution' => 'normal'])
            ->assertStatus(202)
            ->assertJsonPath('queued', 8);

        $this->assertSame([], array_diff($response->json('question_ids'), $ids));
        Queue::assertPushed(EvaluateQuestion::class, 8);
    }

    public function test_valida_cantidad_y_distribucion(): void
    {
        $this->postJson('/api/traffic', ['count' => 51, 'distribution' => 'zipf'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['count', 'distribution']);
    }
}
