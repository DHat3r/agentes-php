<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'qa');
        file_put_contents($path, $content);

        return $path;
    }

    public function test_importa_el_formato_de_yahoo_answers(): void
    {
        $path = $this->csv("\"5\",\"¿Qué es PHP?\",\"Lo vi en un curso\",\"Un lenguaje\\nde servidor\"\n");

        $this->artisan('qa:importar', ['archivo' => $path])
            ->expectsOutput('Se importaron 1 preguntas.')
            ->assertSuccessful();

        $question = Question::sole();
        $this->assertSame('¿Qué es PHP? Lo vi en un curso', $question->question_text);
        $this->assertSame("Un lenguaje\nde servidor", $question->human_answer);
        $this->assertSame('Computación e internet', $question->category);
    }

    public function test_salta_filas_sin_respuesta_y_respeta_el_limite(): void
    {
        $path = $this->csv("\"1\",\"a\",\"\",\"\"\n\"1\",\"b\",\"\",\"r1\"\n\"1\",\"c\",\"\",\"r2\"\n");

        $this->artisan('qa:importar', ['archivo' => $path, '--limite' => 1])->assertSuccessful();

        $this->assertSame(['b'], Question::pluck('question_text')->all());
    }

    public function test_el_seeder_carga_la_muestra_una_sola_vez(): void
    {
        $this->seed();
        $count = Question::count();
        $this->seed();

        $this->assertGreaterThanOrEqual(30, $count);
        $this->assertSame($count, Question::count());
    }
}
