<?php

declare(strict_types=1);

namespace Conciliacion\Http;

use Psr\Http\Message\ResponseInterface as Response;

final class Json
{
    public static function responder(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
