<?php

declare(strict_types=1);

namespace Conciliacion\Http;

use Conciliacion\Dominio\CsvInvalido;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Throwable;

/**
 * Convierte cualquier excepción en una respuesta JSON `{"error": "..."}`.
 * Los errores inesperados devuelven 500 sin detalles internos (se registran en el log).
 */
final class ManejadorErrores
{
    public function __construct(private readonly ResponseFactoryInterface $responses) {}

    public function __invoke(ServerRequestInterface $request, Throwable $error): ResponseInterface
    {
        [$status, $mensaje] = match (true) {
            $error instanceof CsvInvalido => [422, $error->getMessage()],
            $error instanceof HttpException => [$error->getCode(), $error->getMessage()],
            default => [500, 'Error interno del servidor.'],
        };

        if ($status === 500) {
            error_log((string) $error);
        }

        return Json::responder($this->responses->createResponse(), ['error' => $mensaje], $status);
    }
}
