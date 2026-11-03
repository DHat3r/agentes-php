# Conciliación bancaria (Slim 4)

Ver el [README principal](../../README.md) para ejecutar todo.

## Flujo (`src/ProcesarConciliacion.php`)

1. Lee la cartola y las facturas (CSV). Si alguno es inválido, responde 422 sin guardar nada.
2. **Regla determinista** (`src/Dominio/ReglaReferenciaExacta.php`): si la glosa menciona el número de la factura y el monto es igual, concilia sin llamar al LLM.
3. **Agente** (`src/Agente/`): resuelve lo que queda con `listar_movimientos_pendientes`, `buscar_facturas`, `conciliar_movimiento` y `enviar_a_revision`.
4. Lo que el agente no resolvió (o todo, si el modelo falla) pasa a revisión humana con su motivo.

## Por qué Slim

Sin la "magia" de Laravel, se ve cada pieza escrita a mano: el contenedor de dependencias (`config/container.php`), el SQL (`src/Persistencia/ConciliacionRepository.php`), un migrador mínimo (`bin/migrate.php`) y el manejo de errores (`src/Http/ManejadorErrores.php`).

## Formato de los CSV

- Cartola: `fecha,descripcion,monto`. Solo se conservan los abonos (monto > 0).
- Facturas: `numero,cliente,monto,fecha_emision`.

Acepta `,` o `;` como separador, el BOM de Excel y montos con puntos (`1.190.000`). Hay ejemplos en `resources/ejemplos/`.
