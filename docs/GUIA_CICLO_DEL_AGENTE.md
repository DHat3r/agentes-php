# Guía: reescribe el ciclo del agente

El ciclo del agente (`packages/agent-core/src/Agent.php`) es lo que más te van a preguntar en una entrevista. Lo escribí yo en modo activo para avanzar rápido; esta guía es para que lo reescribas tú y lo entiendas a fondo.

**La especificación son los 11 tests de `packages/agent-core/tests/Unit/AgentTest.php`.** No necesitas mirar mi versión: haz pasar los tests uno por uno.

## Preparación

```bash
cd packages/agent-core
git switch -c practica/ciclo-agente
```

Abre `src/Agent.php` y deja `run()` así (borra el cuerpo y `executeTool`):

```php
public function run(string $systemPrompt, string $userMessage): AgentResult
{
    throw new \LogicException('Por implementar');
}
```

Corre los tests; deberían fallar los 11:

```bash
docker run --rm -v "$(pwd -W):/app" -w /app composer:2 vendor/bin/phpunit --filter AgentTest
```

## Pasos (un test o dos por paso)

### 1. Respuesta directa
Test: `test_devuelve_la_respuesta_cuando_el_modelo_no_pide_herramientas`.

Arma el historial con dos mensajes (`system` y `user`), llama una vez a `$this->llm->chat($messages, $this->tools->definitions())` y, si la respuesta no trae herramientas, devuelve un `AgentResult`.

> Pista: `LlmResponse` tiene `hasToolCalls()` y `content`. `Step::finalAnswer()` crea el paso de la traza.

### 2. Ejecutar una herramienta y volver a preguntar
Tests: `test_ejecuta_la_herramienta_y_le_devuelve_el_resultado_al_modelo` y `test_envia_las_definiciones_de_herramientas_al_modelo`.

Ahora necesitas un **bucle**. Si el modelo pide una herramienta:
1. Agrega al historial la respuesta del modelo (`$response->toAssistantMessage()`). Si no lo haces, el modelo "olvida" lo que pidió.
2. Busca la herramienta con `$this->tools->get($call->name)`, decodifica `$call->rawArguments` con `json_decode(..., true)` y ejecútala.
3. Agrega un mensaje `['role' => 'tool', 'tool_call_id' => $call->id, 'content' => json_encode($resultado)]`.
4. Vuelve a llamar al modelo.

> Pregunta para pensar: ¿por qué el mensaje de la herramienta lleva `tool_call_id`?

### 3. Varias herramientas en una respuesta
Test: `test_ejecuta_varias_herramientas_pedidas_en_la_misma_respuesta`.

`$response->toolCalls` es una lista: recórrela completa antes de volver a llamar al modelo.

### 4. Límite de iteraciones
Test: `test_corta_el_ciclo_al_llegar_al_limite_de_iteraciones`.

Usa `for ($i = 1; $i <= $this->maxIterations; $i++)` en vez de `while (true)`. Si sales del `for`, lanza `MaxIterationsReached` con los pasos acumulados.

> Por qué importa: sin límite, un modelo que repite la misma herramienta consume tokens (y dinero) para siempre.

### 5. Errores que el modelo puede corregir
Tests: `test_avisa_al_modelo_si_pide_una_herramienta_inexistente`, `..._si_los_argumentos_no_son_json`, `test_no_ejecuta_la_herramienta_si_los_argumentos_no_cumplen_el_esquema` y `test_devuelve_al_modelo_los_errores_de_negocio_de_la_herramienta`.

En los cuatro casos **no lances una excepción**: devuelve `{"error": "..."}` como resultado de la herramienta, para que el modelo lea el error y lo intente de otra forma.
- Herramienta inexistente: `get()` devuelve `null`.
- JSON inválido: `json_decode` no devuelve un array.
- Esquema: `(new SchemaValidator)->validate($tool->parameters(), $args)` devuelve la lista de errores.
- Negocio: atrapa **solo** `ToolException`.

> Error común: atrapar `\Throwable`. Así escondes bugs reales (un `TypeError` en tu herramienta) como si fueran errores del modelo.

### 6. Errores que el modelo no puede corregir
Tests: `test_propaga_los_errores_del_modelo_para_que_quien_llama_reintente` y `test_falla_si_el_modelo_responde_vacio`.

`LlmException` no se atrapa: debe salir del ciclo. Si el modelo responde sin texto y sin herramientas, lanza `LlmException` tú.

## Cuando pasen los 11

```bash
vendor/bin/pint --test
git diff main -- src/Agent.php   # compara con mi versión
```

Si tu versión te gusta más, haz commit (`refactor(core): reescribe el ciclo del agente`) y corre los tests de las tres apps: tienen que seguir pasando sin tocarlas. Ahí se ve el valor de una interfaz estable.

## Preguntas de entrevista

<details>
<summary>¿Qué diferencia hay entre un chat con un LLM y un agente?</summary>

El chat reenvía mensajes. El agente tiene un ciclo: el modelo decide qué herramienta usar, el código la ejecuta y le devuelve el resultado, y así hasta terminar. El modelo planifica; el código actúa y pone los límites.
</details>

<details>
<summary>¿Cómo testeas código que depende de un modelo no determinista?</summary>

Separo el ciclo del modelo con una interfaz (`LlmClient`). En los tests inyecto `FakeLlmClient`, que sigue un guion fijo de respuestas, así el test es determinista y no llama a la API. Las herramientas se prueban por separado, como código normal.
</details>

<details>
<summary>¿Qué pasa si el modelo inventa un id o pide algo prohibido?</summary>

Las reglas de negocio están en las herramientas, no en el prompt. Por ejemplo, `conciliar_movimiento` verifica que el movimiento y la factura existan, sean de la misma conciliación y tengan el mismo monto. Si no se cumple, el modelo recibe el error y no se rompe ningún dato.
</details>

<details>
<summary>¿Por qué no usar el LLM para todo?</summary>

Es más caro, más lento y puede equivocarse. Lo obvio lo resuelve una regla (por ejemplo, la factura mencionada en la glosa) y el modelo queda solo para lo ambiguo. En el tutor, el nivel lo calcula una regla fija y el modelo solo corrige.
</details>

<details>
<summary>¿Cómo manejas los límites de uso (429) de un modelo gratuito?</summary>

En dos capas. OpenRouter prueba otro modelo de la lista (fallback, máximo 3). Si todos fallan, la `LlmException` sale del agente y quien llama decide: en QA, la cola reintenta con backoff; en el tutor, se responde 503 para que el usuario reintente.
</details>
