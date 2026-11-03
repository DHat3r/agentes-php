<?php

namespace App\Agent\Tools;

use Agentes\Core\Tools\Tool;
use App\Agent\JudgeScores;
use App\Models\Evaluation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Busca respuestas del agente que el juez ya aprobó para preguntas parecidas.
 *
 * Es una búsqueda por palabras clave (sin embeddings) que le da al modelo
 * ejemplos de respuestas que funcionaron, sin mostrarle respuestas humanas.
 */
final class BuscarRespuestasPrevias implements Tool
{
    private const MAX_RESULTS = 3;

    public function __construct(private readonly int $excludeQuestionId) {}

    public function name(): string
    {
        return 'buscar_respuestas_previas';
    }

    public function description(): string
    {
        return 'Busca respuestas aprobadas de preguntas parecidas, útiles como referencia de estilo y contenido.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'texto' => ['type' => 'string', 'minLength' => 3, 'description' => 'Palabras clave de la pregunta'],
            ],
            'required' => ['texto'],
        ];
    }

    public function execute(array $arguments): array
    {
        $keywords = $this->keywords($arguments['texto']);
        if ($keywords === []) {
            return ['resultados' => []];
        }

        $results = Evaluation::query()
            ->with('question:id,question_text')
            ->where('status', Evaluation::COMPLETED)
            ->where('overall_score', '>=', JudgeScores::APPROVAL_THRESHOLD)
            ->where('question_id', '!=', $this->excludeQuestionId)
            ->whereHas('question', function (Builder $query) use ($keywords) {
                $query->where(function (Builder $query) use ($keywords) {
                    foreach ($keywords as $word) {
                        $query->orWhereRaw('LOWER(question_text) LIKE ?', ["%{$word}%"]);
                    }
                });
            })
            ->orderByDesc('overall_score')
            ->limit(self::MAX_RESULTS)
            ->get();

        return ['resultados' => $results->map(fn (Evaluation $evaluation) => [
            'pregunta' => $evaluation->question->question_text,
            'respuesta' => $evaluation->llm_answer,
            'puntaje' => $evaluation->overall_score,
        ])->all()];
    }

    /**
     * Palabras de más de 4 letras: descarta artículos y preposiciones sin una lista de stopwords.
     *
     * @return list<string>
     */
    private function keywords(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), flags: PREG_SPLIT_NO_EMPTY);

        return array_slice(array_values(array_unique(array_filter($words, fn ($w) => mb_strlen($w) > 4))), 0, 5);
    }
}
