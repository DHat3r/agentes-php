<?php

namespace App\Support;

use InvalidArgumentException;
use Random\IntervalBoundary;
use Random\Randomizer;

/**
 * Elige qué preguntas evaluar según una distribución, como el generador de tráfico original.
 *
 * - uniform: todas las preguntas tienen la misma probabilidad.
 * - normal: se concentra en las del medio (media n/2, desviación n/6).
 * - poisson: se concentra en las primeras, como si unas pocas fueran muy populares.
 *
 * Recibe un Randomizer para que los tests usen una semilla fija y sean deterministas.
 */
class TrafficSampler
{
    public const DISTRIBUTIONS = ['uniform', 'normal', 'poisson'];

    public function __construct(private readonly Randomizer $random = new Randomizer) {}

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function sample(array $ids, int $count, string $distribution): array
    {
        if ($ids === []) {
            return [];
        }

        $n = count($ids);
        $picked = [];
        for ($i = 0; $i < $count; $i++) {
            $index = match ($distribution) {
                'uniform' => $this->random->getInt(0, $n - 1),
                'normal' => $this->normal($n / 2, $n / 6),
                'poisson' => $this->poisson(max(1.0, $n / 10)),
                default => throw new InvalidArgumentException("Distribución desconocida: {$distribution}"),
            };
            $picked[] = $ids[max(0, min($n - 1, $index))];
        }

        return $picked;
    }

    /** Transformada de Box-Muller: dos uniformes se convierten en una normal. */
    private function normal(float $mean, float $std): int
    {
        $u1 = $this->random->getFloat(0, 1, IntervalBoundary::OpenClosed);
        $u2 = $this->random->getFloat(0, 1);

        return (int) round($mean + $std * sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2));
    }

    /** Algoritmo de Knuth: sirve para lambdas pequeños como los que usamos. */
    private function poisson(float $lambda): int
    {
        $limit = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= $this->random->getFloat(0, 1);
        } while ($p > $limit);

        return $k - 1;
    }
}
