<?php

declare(strict_types=1);

namespace Conciliacion\Dominio;

/**
 * Lee la cartola del banco y la lista de facturas.
 *
 * Cartola:  fecha,descripcion,monto            (solo se conservan los abonos: monto > 0)
 * Facturas: numero,cliente,monto,fecha_emision
 * Acepta montos con separador de miles chileno ("1.190.000") y separador ; o ,.
 */
final class LectorCsv
{
    private const COLUMNAS_CARTOLA = ['fecha', 'descripcion', 'monto'];

    private const COLUMNAS_FACTURAS = ['numero', 'cliente', 'monto'];

    /** @return list<array{fecha: string, descripcion: string, monto: int}> */
    public function cartola(string $contenido): array
    {
        $movimientos = [];
        foreach ($this->filas($contenido, self::COLUMNAS_CARTOLA, 'cartola') as $linea => $fila) {
            $monto = $this->monto($fila['monto'], $linea, 'cartola');
            if ($monto <= 0) {
                continue; // Los cargos (pagos que salen) no se concilian contra facturas de venta.
            }
            $movimientos[] = [
                'fecha' => $this->requerido($fila['fecha'], 'fecha', $linea, 'cartola'),
                'descripcion' => $this->requerido($fila['descripcion'], 'descripcion', $linea, 'cartola'),
                'monto' => $monto,
            ];
        }

        return $movimientos;
    }

    /** @return list<array{numero: string, cliente: string, monto: int, fecha_emision: ?string}> */
    public function facturas(string $contenido): array
    {
        $facturas = [];
        foreach ($this->filas($contenido, self::COLUMNAS_FACTURAS, 'facturas') as $linea => $fila) {
            $facturas[] = [
                'numero' => $this->requerido($fila['numero'], 'numero', $linea, 'facturas'),
                'cliente' => $this->requerido($fila['cliente'], 'cliente', $linea, 'facturas'),
                'monto' => $this->monto($fila['monto'], $linea, 'facturas'),
                'fecha_emision' => trim($fila['fecha_emision'] ?? '') ?: null,
            ];
        }

        return $facturas;
    }

    /**
     * @return iterable<int, array<string, string>> Número de línea => fila con claves del encabezado.
     */
    private function filas(string $contenido, array $requeridas, string $archivo): iterable
    {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido); // BOM que agrega Excel
        $lineas = preg_split('/\r\n|\n|\r/', trim($contenido));
        if ($lineas === false || $lineas === [''] || $lineas === []) {
            throw new CsvInvalido("El archivo de {$archivo} está vacío.");
        }

        $separador = substr_count($lineas[0], ';') > substr_count($lineas[0], ',') ? ';' : ',';
        $encabezado = array_map(fn ($c) => mb_strtolower(trim($c)), str_getcsv($lineas[0], $separador, escape: ''));
        $faltantes = array_diff($requeridas, $encabezado);
        if ($faltantes !== []) {
            throw new CsvInvalido("Al archivo de {$archivo} le faltan columnas: ".implode(', ', $faltantes).'.');
        }

        foreach (array_slice($lineas, 1, preserve_keys: true) as $indice => $linea) {
            if (trim($linea) === '') {
                continue;
            }
            $valores = str_getcsv($linea, $separador, escape: '');
            $valores = array_pad(array_slice($valores, 0, count($encabezado)), count($encabezado), '');

            yield $indice + 1 => array_combine($encabezado, $valores);
        }
    }

    private function monto(string $valor, int $linea, string $archivo): int
    {
        $limpio = str_replace(['$', '.', ' '], '', trim($valor));
        if (preg_match('/^-?\d+$/', $limpio) !== 1) {
            throw new CsvInvalido("Monto inválido '{$valor}' en la línea {$linea} de {$archivo}.");
        }

        return (int) $limpio;
    }

    private function requerido(string $valor, string $columna, int $linea, string $archivo): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            throw new CsvInvalido("Falta '{$columna}' en la línea {$linea} de {$archivo}.");
        }

        return $valor;
    }
}
