<?php

declare(strict_types=1);

namespace Conciliacion\Persistencia;

use PDO;

/**
 * Todo el SQL de la app vive aquí; el resto del código habla con métodos con nombre de negocio.
 */
final class ConciliacionRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * @param  list<array{fecha: string, descripcion: string, monto: int}>  $movimientos
     * @param  list<array{numero: string, cliente: string, monto: int, fecha_emision: ?string}>  $facturas
     */
    public function crear(array $movimientos, array $facturas): int
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO conciliaciones (creada_en) VALUES (?)')->execute([date(DATE_ATOM)]);
            $id = (int) $this->pdo->lastInsertId();

            $insertarFactura = $this->pdo->prepare('INSERT INTO facturas (conciliacion_id, numero, cliente, monto, fecha_emision) VALUES (?, ?, ?, ?, ?)');
            foreach ($facturas as $f) {
                $insertarFactura->execute([$id, $f['numero'], $f['cliente'], $f['monto'], $f['fecha_emision']]);
            }

            $insertarMovimiento = $this->pdo->prepare('INSERT INTO movimientos (conciliacion_id, fecha, descripcion, monto) VALUES (?, ?, ?, ?)');
            foreach ($movimientos as $m) {
                $insertarMovimiento->execute([$id, $m['fecha'], $m['descripcion'], $m['monto']]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $id;
    }

    public function existe(int $conciliacionId): bool
    {
        return $this->valor('SELECT COUNT(*) FROM conciliaciones WHERE id = ?', [$conciliacionId]) > 0;
    }

    /** @return list<array<string, mixed>> */
    public function movimientosPendientes(int $conciliacionId): array
    {
        return $this->filas(
            "SELECT id, fecha, descripcion, monto FROM movimientos WHERE conciliacion_id = ? AND estado = 'pendiente' ORDER BY id",
            [$conciliacionId],
        );
    }

    /** @return list<array<string, mixed>> */
    public function facturasDisponibles(int $conciliacionId, ?int $montoMin = null, ?int $montoMax = null, ?string $cliente = null): array
    {
        $sql = 'SELECT id, numero, cliente, monto, fecha_emision FROM facturas WHERE conciliacion_id = ? AND usada = 0';
        $params = [$conciliacionId];
        if ($montoMin !== null) {
            $sql .= ' AND monto >= ?';
            $params[] = $montoMin;
        }
        if ($montoMax !== null) {
            $sql .= ' AND monto <= ?';
            $params[] = $montoMax;
        }
        $facturas = $this->filas($sql.' ORDER BY id', $params);

        // El filtro por cliente se hace en PHP porque los bancos escriben sin tildes
        // ("PANADERIA") y LIKE en SQL sí las distingue. Encontrado al probar con el modelo real.
        if ($cliente === null || trim($cliente) === '') {
            return $facturas;
        }
        $buscado = $this->normalizar($cliente);

        return array_values(array_filter($facturas, fn (array $f) => str_contains($this->normalizar($f['cliente']), $buscado)));
    }

    /** Minúsculas, sin tildes y con espacios simples: "Panadería  La Espiga" → "panaderia la espiga". */
    private function normalizar(string $texto): string
    {
        $sinTildes = strtr(mb_strtolower(trim($texto)), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return preg_replace('/\s+/', ' ', $sinTildes);
    }

    public function movimiento(int $conciliacionId, int $movimientoId): ?array
    {
        return $this->filas('SELECT * FROM movimientos WHERE conciliacion_id = ? AND id = ?', [$conciliacionId, $movimientoId])[0] ?? null;
    }

    public function factura(int $conciliacionId, int $facturaId): ?array
    {
        return $this->filas('SELECT * FROM facturas WHERE conciliacion_id = ? AND id = ?', [$conciliacionId, $facturaId])[0] ?? null;
    }

    /** Marca el movimiento como conciliado y la factura como usada, en una sola transacción. */
    public function conciliar(int $movimientoId, int $facturaId, string $motivo, string $origen): void
    {
        $this->pdo->beginTransaction();
        $this->pdo->prepare("UPDATE movimientos SET estado = 'conciliado', factura_id = ?, motivo = ?, origen = ? WHERE id = ?")
            ->execute([$facturaId, $motivo, $origen, $movimientoId]);
        $this->pdo->prepare('UPDATE facturas SET usada = 1 WHERE id = ?')->execute([$facturaId]);
        $this->pdo->commit();
    }

    public function enviarARevision(int $movimientoId, string $motivo, string $origen): void
    {
        $this->pdo->prepare("UPDATE movimientos SET estado = 'revision', motivo = ?, origen = ? WHERE id = ?")
            ->execute([$motivo, $origen, $movimientoId]);
    }

    public function guardarResultadoAgente(int $conciliacionId, array $trace, ?string $error): void
    {
        $this->pdo->prepare('UPDATE conciliaciones SET trace = ?, error = ? WHERE id = ?')
            ->execute([json_encode($trace, JSON_UNESCAPED_UNICODE), $error, $conciliacionId]);
    }

    public function detalle(int $conciliacionId): ?array
    {
        $conciliacion = $this->filas('SELECT * FROM conciliaciones WHERE id = ?', [$conciliacionId])[0] ?? null;
        if ($conciliacion === null) {
            return null;
        }

        $movimientos = $this->filas(
            'SELECT m.id, m.fecha, m.descripcion, m.monto, m.estado, f.numero AS factura_numero, m.motivo, m.origen
             FROM movimientos m LEFT JOIN facturas f ON f.id = m.factura_id
             WHERE m.conciliacion_id = ? ORDER BY m.id',
            [$conciliacionId],
        );
        $facturas = array_map(
            fn (array $f) => [...$f, 'usada' => (bool) $f['usada']],
            $this->filas('SELECT id, numero, cliente, monto, fecha_emision, usada FROM facturas WHERE conciliacion_id = ? ORDER BY id', [$conciliacionId]),
        );

        return [
            'id' => (int) $conciliacion['id'],
            'creada_en' => $conciliacion['creada_en'],
            'resumen' => $this->resumen($movimientos),
            'movimientos' => $movimientos,
            'facturas' => $facturas,
            'trace' => json_decode($conciliacion['trace'] ?? '[]', true) ?? [],
            'error' => $conciliacion['error'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        $filas = $this->filas(
            "SELECT c.id, c.creada_en,
                    COUNT(m.id) AS total_movimientos,
                    SUM(CASE WHEN m.estado = 'conciliado' THEN 1 ELSE 0 END) AS conciliados,
                    SUM(CASE WHEN m.estado = 'revision' THEN 1 ELSE 0 END) AS en_revision,
                    SUM(CASE WHEN m.estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes
             FROM conciliaciones c LEFT JOIN movimientos m ON m.conciliacion_id = c.id
             GROUP BY c.id, c.creada_en ORDER BY c.id DESC",
        );

        return array_map(fn (array $f) => array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $f), $filas);
    }

    private function resumen(array $movimientos): array
    {
        $contar = fn (string $estado) => count(array_filter($movimientos, fn ($m) => $m['estado'] === $estado));

        return [
            'total_movimientos' => count($movimientos),
            'conciliados' => $contar('conciliado'),
            'en_revision' => $contar('revision'),
            'pendientes' => $contar('pendiente'),
        ];
    }

    private function filas(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map($this->tipar(...), $stmt->fetchAll());
    }

    private function valor(string $sql, array $params): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** Postgres y SQLite devuelven tipos distintos; normalizamos ids y montos a int. */
    private function tipar(array $fila): array
    {
        foreach (['id', 'conciliacion_id', 'monto', 'factura_id', 'usada'] as $campo) {
            if (isset($fila[$campo])) {
                $fila[$campo] = (int) $fila[$campo];
            }
        }

        return $fila;
    }
}
