<?php

declare(strict_types=1);

namespace Agentes\Core\Tests\Unit;

use Agentes\Core\Tools\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorTest extends TestCase
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'nombre' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 10],
            'edad' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 120],
            'puntaje' => ['type' => 'number'],
            'activo' => ['type' => 'boolean'],
            'nivel' => ['type' => 'string', 'enum' => ['bajo', 'alto']],
            'tags' => ['type' => 'array'],
        ],
        'required' => ['nombre'],
    ];

    public function test_acepta_argumentos_validos(): void
    {
        $errors = (new SchemaValidator)->validate(self::SCHEMA, [
            'nombre' => 'Ana', 'edad' => 30, 'puntaje' => 0.5, 'activo' => true, 'nivel' => 'alto', 'tags' => ['a'],
        ]);

        $this->assertSame([], $errors);
    }

    public function test_acepta_enteros_donde_se_pide_number(): void
    {
        $this->assertSame([], (new SchemaValidator)->validate(self::SCHEMA, ['nombre' => 'Ana', 'puntaje' => 1]));
    }

    public static function argumentosInvalidos(): array
    {
        return [
            'falta obligatorio' => [[], "Falta el argumento obligatorio 'nombre'"],
            'tipo string' => [['nombre' => 5], "'nombre' debe ser de tipo string"],
            'muy corto' => [['nombre' => ' a '], 'al menos 2'],
            'muy largo' => [['nombre' => str_repeat('x', 11)], 'como máximo 10'],
            'entero con decimales' => [['nombre' => 'Ana', 'edad' => 1.5], "'edad' debe ser de tipo integer"],
            'bajo el mínimo' => [['nombre' => 'Ana', 'edad' => -1], 'mayor o igual a 0'],
            'sobre el máximo' => [['nombre' => 'Ana', 'edad' => 121], 'menor o igual a 120'],
            'fuera del enum' => [['nombre' => 'Ana', 'nivel' => 'medio'], 'uno de: bajo, alto'],
            'boolean como texto' => [['nombre' => 'Ana', 'activo' => 'true'], 'tipo boolean'],
            'objeto en vez de lista' => [['nombre' => 'Ana', 'tags' => ['x' => 1]], 'tipo array'],
        ];
    }

    #[DataProvider('argumentosInvalidos')]
    public function test_rechaza_argumentos_invalidos(array $arguments, string $expected): void
    {
        $errors = (new SchemaValidator)->validate(self::SCHEMA, $arguments);

        $this->assertStringContainsString($expected, implode(' ', $errors));
    }
}
