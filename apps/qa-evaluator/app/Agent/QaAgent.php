<?php

namespace App\Agent;

use Agentes\Core\Agent;
use Agentes\Core\AgentResult;
use Agentes\Core\Llm\LlmClient;
use Agentes\Core\Tools\ToolRegistry;
use App\Agent\Tools\BuscarPregunta;
use App\Agent\Tools\BuscarRespuestasPrevias;
use App\Agent\Tools\EvaluarRespuesta;
use App\Agent\Tools\GuardarResultado;
use App\Models\Evaluation;

/**
 * Arma el agente del evaluador QA: prompt de sistema + herramientas con el contexto de una evaluación.
 */
class QaAgent
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        Eres un agente que responde preguntas de un foro y verifica la calidad de sus respuestas.

        Sigue estos pasos:
        1. Lee la pregunta con buscar_pregunta.
        2. Si te sirve, revisa con buscar_respuestas_previas respuestas aprobadas de preguntas parecidas.
        3. Escribe una respuesta clara, correcta y completa en español, de máximo 200 palabras.
        4. Califícala con evaluar_respuesta. Si no queda aprobada y quedan intentos, mejórala
           según los puntajes (similitud, calidad, completitud) y vuelve a evaluarla.
        5. Guarda con guardar_resultado la respuesta que tuvo el mejor puntaje general.
        6. Termina con un resumen de una línea de lo que hiciste.
        PROMPT;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly AnswerJudge $judge,
        private readonly int $maxIterations = 10,
    ) {}

    public function evaluate(Evaluation $evaluation): AgentResult
    {
        $context = new EvaluationContext($evaluation);

        $tools = new ToolRegistry(
            new BuscarPregunta,
            new BuscarRespuestasPrevias($evaluation->question_id),
            new EvaluarRespuesta($context, $this->judge),
            new GuardarResultado($context),
        );

        return (new Agent($this->llm, $tools, $this->maxIterations))
            ->run(self::SYSTEM_PROMPT, "Evalúa la pregunta con id {$evaluation->question_id}.");
    }
}
