<?php

declare(strict_types=1);

namespace Conciliacion\Tests\Feature;

use Agentes\Core\Llm\FakeLlmClient;
use Agentes\Core\Llm\LlmClient;
use Conciliacion\App;
use Conciliacion\Persistencia\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App as SlimApp;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;

/**
 * Prueba la API completa en memoria: mismas rutas y middleware que en producción,
 * pero con SQLite y un modelo simulado inyectados en el contenedor.
 */
final class ApiTest extends TestCase
{
    private SlimApp $app;

    private FakeLlmClient $llm;

    protected function setUp(): void
    {
        $pdo = Database::enMemoria();
        Database::migrar($pdo, __DIR__.'/../../migrations');

        $contenedor = App::contenedor();
        $contenedor->set(PDO::class, $pdo);
        $contenedor->set(LlmClient::class, $this->llm = new FakeLlmClient);
        $this->app = App::crear($contenedor);
    }

    private function request(string $metodo, string $ruta, array $archivos = []): ResponseInterface
    {
        $request = (new ServerRequestFactory)->createServerRequest($metodo, $ruta);
        $subidos = [];
        foreach ($archivos as $campo => $contenido) {
            $subidos[$campo] = new UploadedFile((new StreamFactory)->createStream($contenido), "{$campo}.csv", 'text/csv', strlen($contenido));
        }

        return $this->app->handle($request->withUploadedFiles($subidos));
    }

    private function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true);
    }

    private function ejemplo(string $tipo): string
    {
        return file_get_contents(__DIR__."/../../resources/ejemplos/{$tipo}.csv");
    }

    public function test_concilia_los_ejemplos_con_la_regla_y_el_agente(): void
    {
        $this->llm->push(
            FakeLlmClient::toolCall('conciliar_movimiento', ['movimiento_id' => 2, 'factura_id' => 2, 'motivo' => 'Panadería La Espiga, mismo monto']),
            FakeLlmClient::answer('Listo.'),
        );

        $response = $this->request('POST', '/api/conciliaciones', ['cartola' => $this->ejemplo('cartola'), 'facturas' => $this->ejemplo('facturas')]);

        $this->assertSame(201, $response->getStatusCode());
        $cuerpo = $this->json($response);
        $this->assertSame(7, $cuerpo['resumen']['total_movimientos']); // el cargo de la luz no cuenta
        $this->assertSame(3, $cuerpo['resumen']['conciliados']);       // 2 por regla + 1 por el agente
        $this->assertSame(4, $cuerpo['resumen']['en_revision']);
    }

    public function test_lista_y_muestra_conciliaciones(): void
    {
        $this->llm->push(FakeLlmClient::answer('Listo.'));
        $id = $this->json($this->request('POST', '/api/conciliaciones', ['cartola' => $this->ejemplo('cartola'), 'facturas' => $this->ejemplo('facturas')]))['id'];

        $this->assertSame($id, $this->json($this->request('GET', '/api/conciliaciones'))[0]['id']);
        $this->assertCount(8, $this->json($this->request('GET', "/api/conciliaciones/{$id}"))['facturas']);
    }

    public function test_responde_422_si_falta_un_archivo(): void
    {
        $response = $this->request('POST', '/api/conciliaciones', ['cartola' => $this->ejemplo('cartola')]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(['error' => "Falta el archivo 'facturas'."], $this->json($response));
    }

    public function test_responde_422_si_el_csv_es_invalido(): void
    {
        $response = $this->request('POST', '/api/conciliaciones', ['cartola' => "fecha,monto\n1,2", 'facturas' => $this->ejemplo('facturas')]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('faltan columnas', $this->json($response)['error']);
    }

    public function test_responde_404_en_json(): void
    {
        $this->assertSame(404, $this->request('GET', '/api/conciliaciones/99')->getStatusCode());
        $this->assertArrayHasKey('error', $this->json($this->request('GET', '/api/no-existe')));
    }

    public function test_descarga_los_csv_de_ejemplo(): void
    {
        $response = $this->request('GET', '/api/ejemplos/cartola');

        $this->assertStringStartsWith('text/csv', $response->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith('fecha,descripcion,monto', (string) $response->getBody());
        $this->assertSame(404, $this->request('GET', '/api/ejemplos/otro')->getStatusCode());
    }
}
