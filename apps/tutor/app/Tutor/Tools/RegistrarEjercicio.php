<?php

namespace App\Tutor\Tools;

use Agentes\Core\Exceptions\ToolException;
use Agentes\Core\Tools\Tool;
use App\Tutor\GenerationContext;
use App\Tutor\LevelPolicy;

/**
 * Guarda el ejercicio que inventó el modelo. Verifica que sea del nivel real del estudiante:
 * así el modelo no puede "decidir" darle algo más fácil o más difícil.
 */
final class RegistrarEjercicio implements Tool
{
    public function __construct(private readonly GenerationContext $context) {}

    public function name(): string
    {
        return 'registrar_ejercicio';
    }

    public function description(): string
    {
        return 'Guarda el ejercicio generado. Debe ser del nivel actual del estudiante. Solo se puede usar una vez.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'nivel' => ['type' => 'integer', 'minimum' => LevelPolicy::MIN_LEVEL, 'maximum' => LevelPolicy::MAX_LEVEL],
                'enunciado' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 1000],
                'respuesta_esperada' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
            ],
            'required' => ['nivel', 'enunciado', 'respuesta_esperada'],
        ];
    }

    public function execute(array $arguments): array
    {
        if ($this->context->exercise !== null) {
            throw new ToolException('Ya registraste un ejercicio en esta sesión.');
        }

        $level = $this->context->progress()->level;
        if ($arguments['nivel'] !== $level) {
            throw new ToolException("El estudiante está en nivel {$level}; genera un ejercicio de ese nivel.");
        }

        $this->context->exercise = $this->context->student->exercises()->create([
            'topic' => $this->context->topic,
            'level' => $level,
            'statement' => trim($arguments['enunciado']),
            'expected_answer' => trim($arguments['respuesta_esperada']),
        ]);

        return ['ejercicio_id' => $this->context->exercise->id];
    }
}
