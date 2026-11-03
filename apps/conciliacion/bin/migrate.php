<?php

declare(strict_types=1);

use Conciliacion\Persistencia\Database;

require __DIR__.'/../vendor/autoload.php';

// Uso: php bin/migrate.php   (lee DB_HOST, DB_DATABASE, DB_USERNAME y DB_PASSWORD del entorno)
$aplicadas = Database::migrar(Database::conectar(getenv()), __DIR__.'/../migrations');

echo $aplicadas === []
    ? "No hay migraciones pendientes.\n"
    : 'Migraciones aplicadas: '.implode(', ', $aplicadas)."\n";
