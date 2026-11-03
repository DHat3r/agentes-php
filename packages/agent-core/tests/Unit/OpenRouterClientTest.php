<?php

declare(strict_types=1);

namespace Agentes\Core\Tests\Unit;

use Agentes\Core\Exceptions\LlmException;
use Agentes\Core\Llm\OpenRouterClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class OpenRouterClientTest extends TestCase
{
    private array $history = [];

    private function client(Response|\Throwable ...$responses): OpenRouterClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new OpenRouterClient('test-key', 'modelo/x', new Client(['handler' => $stack]));
    }

    private static function json(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    public function test_envia_modelo_mensajes_herramientas_y_autorizacion(): void
    {
        $tools = [['type' => 'function', 'function' => ['name' => 'sumar']]];
        $this->client(self::json(['choices' => [['message' => ['content' => 'hola']]]]))
            ->chat([['role' => 'user', 'content' => 'hola']], $tools);

        $request = $this->history[0]['request'];
        $payload = json_decode((string) $request->getBody(), true);
        $this->assertSame('Bearer test-key', $request->getHeaderLine('Authorization'));
        $this->assertSame('modelo/x', $payload['model']);
        $this->assertSame($tools, $payload['tools']);
        $this->assertSame('auto', $payload['tool_choice']);
    }

    public function test_no_envia_tools_si_no_hay_herramientas(): void
    {
        $this->client(self::json(['choices' => [['message' => ['content' => 'hola']]]]))->chat([]);

        $payload = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertArrayNotHasKey('tools', $payload);
    }

    public function test_interpreta_pedidos_de_herramientas(): void
    {
        $response = $this->client(self::json(['choices' => [['message' => [
            'content' => null,
            'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'sumar', 'arguments' => '{"a":1}']]],
        ]]]]))->chat([]);

        $this->assertTrue($response->hasToolCalls());
        $this->assertSame('sumar', $response->toolCalls[0]->name);
        $this->assertSame('{"a":1}', $response->toolCalls[0]->rawArguments);
    }

    public function test_convierte_errores_http_en_llm_exception(): void
    {
        $this->expectException(LlmException::class);
        $this->expectExceptionMessage('HTTP 429: rate limit');

        $this->client(self::json(['error' => ['message' => 'rate limit']], 429))->chat([]);
    }

    public function test_convierte_timeouts_en_llm_exception(): void
    {
        $this->expectException(LlmException::class);

        $this->client(new ConnectException('timeout', new Request('POST', 'x')))->chat([]);
    }

    public function test_rechaza_respuestas_sin_choices(): void
    {
        $this->expectException(LlmException::class);

        $this->client(self::json(['foo' => 'bar']))->chat([]);
    }

    public function test_exige_api_key(): void
    {
        $this->expectException(LlmException::class);

        new OpenRouterClient('', 'modelo/x');
    }
}
