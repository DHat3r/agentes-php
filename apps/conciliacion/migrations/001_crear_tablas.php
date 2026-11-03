<?php

declare(strict_types=1);

// Montos en pesos chilenos como enteros: el CLP no usa decimales y así evitamos errores de redondeo.
return function (PDO $pdo, string $id): void {
    $pdo->exec("CREATE TABLE conciliaciones (
        id {$id},
        creada_en VARCHAR(32) NOT NULL,
        trace TEXT,
        error TEXT
    )");

    $pdo->exec("CREATE TABLE facturas (
        id {$id},
        conciliacion_id INTEGER NOT NULL REFERENCES conciliaciones(id) ON DELETE CASCADE,
        numero VARCHAR(50) NOT NULL,
        cliente VARCHAR(255) NOT NULL,
        monto BIGINT NOT NULL,
        fecha_emision VARCHAR(10),
        usada INTEGER NOT NULL DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE movimientos (
        id {$id},
        conciliacion_id INTEGER NOT NULL REFERENCES conciliaciones(id) ON DELETE CASCADE,
        fecha VARCHAR(10) NOT NULL,
        descripcion VARCHAR(255) NOT NULL,
        monto BIGINT NOT NULL,
        estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
        factura_id INTEGER REFERENCES facturas(id),
        motivo TEXT,
        origen VARCHAR(20)
    )");

    $pdo->exec('CREATE INDEX idx_movimientos_conciliacion ON movimientos (conciliacion_id, estado)');
    $pdo->exec('CREATE INDEX idx_facturas_conciliacion ON facturas (conciliacion_id, usada, monto)');
};
