<?php

declare(strict_types=1);

namespace Conciliacion\Dominio;

use InvalidArgumentException;

/** El archivo subido no tiene el formato esperado. La API responde 422 con el mensaje. */
final class CsvInvalido extends InvalidArgumentException {}
