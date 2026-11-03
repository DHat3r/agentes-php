<?php

declare(strict_types=1);

namespace Agentes\Core\Tests\Unit;

use Agentes\Core\Tests\Support\SumTool;
use Agentes\Core\Tools\ToolRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ToolRegistryTest extends TestCase
{
    public function test_genera_definiciones_en_formato_openai(): void
    {
        $definitions = (new ToolRegistry(new SumTool))->definitions();

        $this->assertSame('function', $definitions[0]['type']);
        $this->assertSame('sumar', $definitions[0]['function']['name']);
        $this->assertSame(['a', 'b'], $definitions[0]['function']['parameters']['required']);
    }

    public function test_busca_herramientas_por_nombre(): void
    {
        $registry = new ToolRegistry(new SumTool);

        $this->assertInstanceOf(SumTool::class, $registry->get('sumar'));
        $this->assertNull($registry->get('restar'));
    }

    public function test_rechaza_nombres_duplicados(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ToolRegistry(new SumTool, new SumTool);
    }
}
