<?php

declare(strict_types=1);

namespace Conciliacion\Tests\Support;

use Conciliacion\Persistencia\ConciliacionRepository;
use Conciliacion\Persistencia\Database;
use PDO;

/**
 * Base SQLite en memoria, migrada desde cero en cada test: rápida y aislada.
 */
trait ConBaseDeDatos
{
    protected PDO $pdo;

    protected ConciliacionRepository $repo;

    protected function prepararBaseDeDatos(): void
    {
        $this->pdo = Database::enMemoria();
        Database::migrar($this->pdo, __DIR__.'/../../migrations');
        $this->repo = new ConciliacionRepository($this->pdo);
    }

    protected function movimiento(string $descripcion, int $monto, string $fecha = '2026-10-01'): array
    {
        return ['fecha' => $fecha, 'descripcion' => $descripcion, 'monto' => $monto];
    }

    protected function factura(string $numero, string $cliente, int $monto): array
    {
        return ['numero' => $numero, 'cliente' => $cliente, 'monto' => $monto, 'fecha_emision' => '2026-09-30'];
    }
}
