<?php

namespace Tests\Unit;

use App\Support\TrafficSampler;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class TrafficSamplerTest extends TestCase
{
    private function sampler(): TrafficSampler
    {
        return new TrafficSampler(new Randomizer(new Mt19937(42)));
    }

    public static function distributions(): array
    {
        return array_map(fn ($d) => [$d], array_combine(TrafficSampler::DISTRIBUTIONS, TrafficSampler::DISTRIBUTIONS));
    }

    #[DataProvider('distributions')]
    public function test_solo_devuelve_ids_existentes(string $distribution): void
    {
        $ids = range(101, 200);

        $picked = $this->sampler()->sample($ids, 500, $distribution);

        $this->assertCount(500, $picked);
        $this->assertSame([], array_diff($picked, $ids));
    }

    public function test_es_determinista_con_la_misma_semilla(): void
    {
        $ids = range(1, 100);

        $this->assertSame(
            $this->sampler()->sample($ids, 20, 'normal'),
            $this->sampler()->sample($ids, 20, 'normal'),
        );
    }

    public function test_normal_se_concentra_en_el_medio(): void
    {
        $picked = $this->sampler()->sample(range(1, 100), 1000, 'normal');
        $middle = array_filter($picked, fn ($id) => $id > 25 && $id <= 75);

        $this->assertGreaterThan(800, count($middle)); // ~95 % cae a ±1.5 desviaciones
    }

    public function test_poisson_se_concentra_en_las_primeras(): void
    {
        $picked = $this->sampler()->sample(range(1, 100), 1000, 'poisson');

        $this->assertLessThan(25, array_sum($picked) / count($picked));
    }

    public function test_sin_preguntas_no_devuelve_nada(): void
    {
        $this->assertSame([], $this->sampler()->sample([], 5, 'uniform'));
    }

    public function test_rechaza_distribuciones_desconocidas(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sampler()->sample([1], 1, 'zipf');
    }
}
