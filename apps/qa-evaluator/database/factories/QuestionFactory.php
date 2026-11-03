<?php

namespace Database\Factories;

use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_text' => fake()->sentence().'?',
            'human_answer' => fake()->paragraph(),
            'category' => fake()->randomElement(['Ciencia', 'Salud', 'Computación']),
        ];
    }
}
