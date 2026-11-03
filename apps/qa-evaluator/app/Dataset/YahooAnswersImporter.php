<?php

namespace App\Dataset;

use App\Models\Question;
use InvalidArgumentException;

/**
 * Importa preguntas en el formato del dataset Yahoo Answers de Kaggle:
 * CSV sin encabezado con columnas `clase, título, contenido, mejor respuesta`.
 */
class YahooAnswersImporter
{
    public const CATEGORIES = [
        1 => 'Sociedad y cultura', 2 => 'Ciencia y matemáticas', 3 => 'Salud',
        4 => 'Educación', 5 => 'Computación e internet', 6 => 'Deportes',
        7 => 'Negocios y finanzas', 8 => 'Entretenimiento y música',
        9 => 'Familia y relaciones', 10 => 'Política y gobierno',
    ];

    /**
     * @return int Cantidad de preguntas importadas.
     */
    public function import(string $path, int $limit = PHP_INT_MAX): int
    {
        $handle = is_readable($path) ? fopen($path, 'r') : false;
        if ($handle === false) {
            throw new InvalidArgumentException("No se puede leer el archivo {$path}.");
        }

        $imported = 0;
        try {
            while ($imported < $limit && ($row = fgetcsv($handle, escape: '')) !== false) {
                if (count($row) < 4 || trim($row[3]) === '') {
                    continue; // Filas incompletas o sin respuesta humana no sirven para comparar.
                }
                Question::create([
                    'question_text' => $this->clean(trim($row[1].' '.$row[2])),
                    'human_answer' => $this->clean($row[3]),
                    'category' => self::CATEGORIES[(int) $row[0]] ?? null,
                ]);
                $imported++;
            }
        } finally {
            fclose($handle);
        }

        return $imported;
    }

    /** El dataset trae los saltos de línea escritos como el texto "\n". */
    private function clean(string $text): string
    {
        return trim(str_replace(['\\n', '<br />'], "\n", $text));
    }
}
