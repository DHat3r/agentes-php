<?php

namespace App\Agent;

use Agentes\Core\Exceptions\LlmException;
use Agentes\Core\Llm\LlmClient;

/**
 * "LLM como juez": compara la respuesta del agente con la respuesta humana.
 *
 * Es una llamada simple al modelo (sin herramientas); el agente la usa dentro
 * de la herramienta evaluar_respuesta.
 */
class AnswerJudge
{
    private const PROMPT = <<<'PROMPT'
        Eres un evaluador estricto. Compara la respuesta candidata con la respuesta humana de referencia.

        Pregunta: %s
        Respuesta humana: %s
        Respuesta candidata: %s

        Devuelve SOLO un objeto JSON, sin texto adicional, con números entre 0 y 1:
        {"similarity_score": 0.0, "quality_score": 0.0, "completeness_score": 0.0}
        PROMPT;

    public function __construct(private readonly LlmClient $llm) {}

    /**
     * @throws LlmException si el juez no devuelve un JSON con los tres puntajes.
     */
    public function judge(string $question, string $humanAnswer, string $candidate): JudgeScores
    {
        $response = $this->llm->chat([
            ['role' => 'user', 'content' => sprintf(self::PROMPT, $question, $humanAnswer, $candidate)],
        ]);

        $scores = $this->extractJson($response->content ?? '');

        return new JudgeScores(
            $this->score($scores, 'similarity_score'),
            $this->score($scores, 'quality_score'),
            $this->score($scores, 'completeness_score'),
        );
    }

    /**
     * Los modelos a veces envuelven el JSON en texto o en ```json```; tomamos el primer objeto.
     */
    private function extractJson(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $match) === 1) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new LlmException('El juez no devolvió un JSON válido: '.mb_substr($text, 0, 120));
    }

    private function score(array $scores, string $key): float
    {
        if (! isset($scores[$key]) || ! is_numeric($scores[$key])) {
            throw new LlmException("El juez no devolvió '{$key}'.");
        }

        return max(0.0, min(1.0, (float) $scores[$key]));
    }
}
