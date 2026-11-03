<?php

declare(strict_types=1);

namespace Agentes\Core\Tools;

use Agentes\Core\Exceptions\ToolException;

/**
 * Una acción que el modelo puede pedir. Cada app implementa las suyas.
 */
interface Tool
{
    /** Nombre en snake_case que verá el modelo, por ejemplo `buscar_pregunta`. */
    public function name(): string;

    /** Explica al modelo cuándo usarla; de este texto depende que la elija bien. */
    public function description(): string;

    /**
     * JSON Schema de los argumentos. SchemaValidator lo revisa antes de llamar a execute().
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * @param  array<string, mixed>  $arguments  Ya validados contra parameters().
     * @return array<string, mixed> Resultado que se devuelve al modelo como JSON.
     *
     * @throws ToolException para errores que el modelo debe conocer y corregir.
     */
    public function execute(array $arguments): array;
}
