<?php

declare(strict_types=1);

namespace Conciliacion\Http;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

/**
 * Entrega los CSV de ejemplo para probar la app sin preparar archivos propios.
 */
final class EjemplosController
{
    private const ARCHIVOS = ['cartola', 'facturas'];

    public function descargar(Request $request, Response $response, array $args): Response
    {
        if (! in_array($args['tipo'], self::ARCHIVOS, true)) {
            throw new HttpNotFoundException($request, 'Ejemplo desconocido.');
        }

        $response->getBody()->write((string) file_get_contents(__DIR__."/../../resources/ejemplos/{$args['tipo']}.csv"));

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$args['tipo']}.csv\"");
    }
}
