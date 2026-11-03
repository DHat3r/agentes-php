<?php

declare(strict_types=1);

namespace Conciliacion\Persistencia;

use PDO;

/**
 * Conexión PDO y un migrador mínimo.
 *
 * Sin ORM a propósito: en esta app se ve cada consulta SQL. Cada migración es un
 * archivo PHP que recibe el PDO y el tipo de columna id, porque Postgres (producción)
 * y SQLite (tests) escriben distinto el autoincremento.
 */
final class Database
{
    public static function conectar(array $env): PDO
    {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $env['DB_HOST'] ?? 'postgres', $env['DB_PORT'] ?? '5432', $env['DB_DATABASE'] ?? 'conciliacion');

        return self::configurar(new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? ''));
    }

    public static function enMemoria(): PDO
    {
        return self::configurar(new PDO('sqlite::memory:'));
    }

    /**
     * Aplica en orden las migraciones que aún no se ejecutaron.
     *
     * @return list<string> Nombres de las migraciones aplicadas ahora.
     */
    public static function migrar(PDO $pdo, string $directorio): array
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS migraciones (nombre VARCHAR(255) PRIMARY KEY, aplicada_en VARCHAR(32) NOT NULL)');
        $aplicadas = $pdo->query('SELECT nombre FROM migraciones')->fetchAll(PDO::FETCH_COLUMN);
        $id = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? 'BIGSERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $nuevas = [];
        $archivos = glob($directorio.'/*.php');
        sort($archivos);
        foreach ($archivos as $archivo) {
            $nombre = basename($archivo, '.php');
            if (in_array($nombre, $aplicadas, true)) {
                continue;
            }
            $pdo->beginTransaction();
            (require $archivo)($pdo, $id);
            $pdo->prepare('INSERT INTO migraciones (nombre, aplicada_en) VALUES (?, ?)')->execute([$nombre, date(DATE_ATOM)]);
            $pdo->commit();
            $nuevas[] = $nombre;
        }

        return $nuevas;
    }

    private static function configurar(PDO $pdo): PDO
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);

        return $pdo;
    }
}
