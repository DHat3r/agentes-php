<?php

declare(strict_types=1);

namespace Agentes\Core\Exceptions;

use RuntimeException;

/**
 * Error de negocio dentro de una herramienta ("la factura ya está usada").
 *
 * El agente no la deja escapar: se la devuelve al modelo como resultado de la
 * herramienta para que corrija su plan. Cualquier otra excepción es un bug y sí escapa.
 */
final class ToolException extends RuntimeException {}
