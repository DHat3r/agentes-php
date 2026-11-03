<?php

declare(strict_types=1);

namespace Agentes\Core\Exceptions;

use RuntimeException;

/**
 * Falla del modelo o de la red (timeout, HTTP 5xx, JSON inválido).
 * Es un error transitorio: quien llama al agente decide si reintenta.
 */
final class LlmException extends RuntimeException {}
