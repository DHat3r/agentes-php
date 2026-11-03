<?php

namespace App\Tutor;

use Agentes\Core\Agent;
use Agentes\Core\AgentResult;
use Agentes\Core\Llm\LlmClient;
use Agentes\Core\Tools\ToolRegistry;
use App\Models\Exercise;
use App\Models\Student;
use App\Tutor\Tools\AjustarNivel;
use App\Tutor\Tools\ConsultarProgreso;
use App\Tutor\Tools\Corregir;
use App\Tutor\Tools\ObtenerEjercicio;
use App\Tutor\Tools\RegistrarEjercicio;

/**
 * Tutor adaptativo con dos tareas, cada una con su propio prompt y herramientas.
 */
class TutorAgent
{
    private const GENERATE_PROMPT = <<<'PROMPT'
        Eres un tutor de matemáticas para escolares chilenos.
        1. Revisa con consultar_progreso el nivel del estudiante, la guía de niveles y sus ejercicios recientes.
        2. Inventa UN ejercicio nuevo, distinto a los recientes, del nivel exacto del estudiante.
           Usa contextos cotidianos de Chile (pesos, micro, feria). No incluyas la respuesta en el enunciado.
        3. Guárdalo con registrar_ejercicio. La respuesta esperada debe ser corta: un número o una expresión.
        4. Termina con una frase breve de ánimo.
        PROMPT;

    private const GRADE_PROMPT = <<<'PROMPT'
        Eres un tutor de matemáticas amable y preciso.
        1. Lee el ejercicio con obtener_ejercicio.
        2. Decide si la respuesta del estudiante es correcta. Acepta formas equivalentes
           (0,5 = 1/2 = 50 %, "x = 3" = "3") y errores de formato menores.
        3. Regístralo con corregir. Si está mal, explica el error y el procedimiento correcto en
           2 o 3 frases, sin dar solo el resultado. Si está bien, refuerza lo que hizo bien.
        4. Llama a ajustar_nivel y termina.
        PROMPT;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly LevelPolicy $policy,
        private readonly int $maxIterations = 8,
    ) {}

    /**
     * @return array{0: Exercise, 1: AgentResult}
     *
     * @throws AgentIncomplete si el modelo no registró el ejercicio.
     */
    public function generate(Student $student, string $topic): array
    {
        $context = new GenerationContext($student, $topic);
        $result = $this->agent(new ConsultarProgreso($context), new RegistrarEjercicio($context))
            ->run(self::GENERATE_PROMPT, "Crea un ejercicio de {$topic} para {$student->name}.");

        if ($context->exercise === null) {
            throw new AgentIncomplete('El agente no generó un ejercicio. Intenta de nuevo.');
        }
        $context->exercise->update(['trace' => $result->trace()]);

        return [$context->exercise, $result];
    }

    /**
     * @return array{0: Exercise, 1: AgentResult, 2: int} Ejercicio corregido, resultado y nivel nuevo.
     *
     * @throws AgentIncomplete si el modelo no corrigió.
     */
    public function grade(Exercise $exercise, string $answer): array
    {
        $exercise->update(['student_answer' => trim($answer)]);
        $context = new GradingContext($exercise);

        $result = $this->agent(new ObtenerEjercicio($context), new Corregir($context), new AjustarNivel($context, $this->policy))
            ->run(self::GRADE_PROMPT, 'Corrige la respuesta del estudiante.');

        if (! $context->isGraded()) {
            throw new AgentIncomplete('El agente no corrigió la respuesta. Intenta de nuevo.');
        }
        // Red de seguridad: si el modelo olvidó ajustar el nivel, la regla se aplica igual.
        if (! $exercise->level_adjusted) {
            (new AjustarNivel($context, $this->policy))->execute([]);
        }
        $exercise->update(['trace' => $result->trace()]);

        return [$exercise, $result, $exercise->student->progressFor($exercise->topic)->level];
    }

    private function agent(\Agentes\Core\Tools\Tool ...$tools): Agent
    {
        return new Agent($this->llm, new ToolRegistry(...$tools), $this->maxIterations);
    }
}
