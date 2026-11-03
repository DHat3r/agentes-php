<?php

declare(strict_types=1);

namespace Agentes\Core\Llm;

use Agentes\Core\Exceptions\LlmException;

/**
 * Puerto hacia cualquier modelo de lenguaje.
 *
 * El agente solo conoce esta interfaz: en producción se inyecta OpenRouterClient
 * y en los tests FakeLlmClient, sin tocar el ciclo del agente.
 */
interface LlmClient
{
    /**
     * @param  list<array<string, mixed>>  $messages  Historial en formato chat de OpenAI.
     * @param  list<array<string, mixed>>  $tools  Definiciones de herramientas (ToolRegistry::definitions()).
     *
     * @throws LlmException si el modelo no responde, tarda demasiado o devuelve algo inválido.
     */
    public function chat(array $messages, array $tools = []): LlmResponse;
}
