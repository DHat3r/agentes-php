<?php

namespace Database\Factories;

use App\Models\Evaluation;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'status' => Evaluation::QUEUED,
        ];
    }

    public function completed(float $overall = 0.8): static
    {
        return $this->state(fn () => [
            'status' => Evaluation::COMPLETED,
            'llm_answer' => fake()->paragraph(),
            'similarity_score' => $overall,
            'quality_score' => $overall,
            'completeness_score' => $overall,
            'overall_score' => $overall,
            'attempts' => 1,
            'completed_at' => now(),
        ]);
    }
}
