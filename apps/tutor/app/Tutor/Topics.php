<?php

namespace App\Tutor;

/**
 * Temas disponibles y qué significa cada nivel. El modelo recibe esta guía
 * para que "nivel 3 de fracciones" signifique siempre lo mismo.
 */
final class Topics
{
    public const ALL = [
        'fracciones' => 'Nivel 1: sumar fracciones con igual denominador. Nivel 5: problemas con operaciones combinadas y fracciones mixtas.',
        'porcentajes' => 'Nivel 1: calcular el 10 % o el 50 % de un número. Nivel 5: aumentos y descuentos sucesivos en problemas de compra.',
        'ecuaciones' => 'Nivel 1: x + a = b. Nivel 5: ecuaciones con paréntesis y la incógnita en ambos lados.',
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::ALL);
    }

    public static function guide(string $topic): string
    {
        return self::ALL[$topic];
    }
}
