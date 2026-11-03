<?php

declare(strict_types=1);

namespace Conciliacion\Http;

use Conciliacion\Dominio\CsvInvalido;
use Conciliacion\Persistencia\ConciliacionRepository;
use Conciliacion\ProcesarConciliacion;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Exception\HttpNotFoundException;

final class ConciliacionController
{
    private const MAX_BYTES = 1_000_000;

    public function __construct(
        private readonly ConciliacionRepository $repo,
        private readonly ProcesarConciliacion $procesar,
    ) {}

    public function listar(Request $request, Response $response): Response
    {
        return Json::responder($response, $this->repo->listar());
    }

    public function ver(Request $request, Response $response, array $args): Response
    {
        $detalle = $this->repo->detalle((int) $args['id']);
        if ($detalle === null) {
            throw new HttpNotFoundException($request, 'La conciliación no existe.');
        }

        return Json::responder($response, $detalle);
    }

    /** Recibe dos archivos multipart: `cartola` y `facturas`. */
    public function crear(Request $request, Response $response): Response
    {
        $archivos = $request->getUploadedFiles();
        $id = ($this->procesar)(
            $this->leer($archivos['cartola'] ?? null, 'cartola'),
            $this->leer($archivos['facturas'] ?? null, 'facturas'),
        );

        return Json::responder($response, $this->repo->detalle($id), 201);
    }

    private function leer(?UploadedFileInterface $archivo, string $campo): string
    {
        if ($archivo === null || $archivo->getError() !== UPLOAD_ERR_OK) {
            throw new CsvInvalido("Falta el archivo '{$campo}'.");
        }
        if ($archivo->getSize() > self::MAX_BYTES) {
            throw new CsvInvalido("El archivo '{$campo}' supera 1 MB.");
        }

        return (string) $archivo->getStream();
    }
}
