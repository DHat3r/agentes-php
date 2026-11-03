<?php

declare(strict_types=1);

namespace Agentes\Core\Llm;

use Agentes\Core\Exceptions\LlmException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Adaptador para OpenRouter, que expone una API compatible con la de OpenAI.
 *
 * `$model` acepta varios modelos separados por coma ("a:free,b:free"): se envían en
 * `models` y OpenRouter usa el siguiente si el primero falla o llega a su límite de uso.
 * Es útil con modelos gratuitos, que suelen responder 429 en horas de mucho tráfico.
 */
final class OpenRouterClient implements LlmClient
{
    /** Límite de la API: con más modelos responde HTTP 400 (comprobado contra la API real). */
    public const MAX_MODELS = 3;

    private readonly ClientInterface $http;

    /** @var list<string> */
    private readonly array $models;

    public function __construct(
        private readonly string $apiKey,
        string $model,
        ?ClientInterface $http = null,
        float $timeoutSeconds = 60,
        string $baseUri = 'https://openrouter.ai/api/v1/',
    ) {
        if ($apiKey === '') {
            throw new LlmException('Falta OPENROUTER_API_KEY. Cópiala en .env (ver .env.example).');
        }
        $this->models = array_values(array_filter(array_map('trim', explode(',', $model))));
        if ($this->models === []) {
            throw new LlmException('Falta OPENROUTER_MODEL.');
        }
        if (count($this->models) > self::MAX_MODELS) {
            throw new LlmException('OpenRouter acepta como máximo '.self::MAX_MODELS.' modelos en OPENROUTER_MODEL.');
        }
        $this->http = $http ?? new Client(['base_uri' => $baseUri, 'timeout' => $timeoutSeconds]);
    }

    public function chat(array $messages, array $tools = []): LlmResponse
    {
        $payload = ['model' => $this->models[0], 'messages' => $messages];
        if (count($this->models) > 1) {
            $payload['models'] = $this->models;
        }
        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = $this->http->request('POST', 'chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'X-Title' => 'agentes-php',
                ],
                'json' => $payload,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            // Incluye timeouts y errores de conexión.
            throw new LlmException('No se pudo contactar a OpenRouter: '.$e->getMessage(), previous: $e);
        }

        $body = json_decode((string) $response->getBody(), true);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            $detail = is_array($body) ? ($body['error']['message'] ?? 'sin detalle') : 'sin detalle';
            throw new LlmException("OpenRouter respondió HTTP {$status}: {$detail}");
        }
        if (! is_array($body) || ! isset($body['choices'][0]['message'])) {
            throw new LlmException('OpenRouter devolvió una respuesta sin choices.');
        }

        return LlmResponse::fromOpenAiMessage($body['choices'][0]['message']);
    }
}
