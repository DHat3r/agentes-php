<?php

namespace App\Support;

use App\Models\Evaluation;
use Illuminate\Support\Facades\Cache;

/**
 * Caché de evaluaciones completadas por pregunta (Redis en Docker).
 *
 * Reemplaza al CacheManager del proyecto original: en vez de implementar
 * LRU a mano, Redis lo hace con `maxmemory-policy allkeys-lru` (ver docker-compose).
 */
class EvaluationCache
{
    private const HITS = 'qa:cache:hits';

    private const MISSES = 'qa:cache:misses';

    public function __construct(private readonly int $ttlSeconds = 3600) {}

    public function remember(Evaluation $evaluation): void
    {
        Cache::put($this->key($evaluation->question_id), $evaluation->id, $this->ttlSeconds);
    }

    public function lookup(int $questionId): ?Evaluation
    {
        $id = Cache::get($this->key($questionId));
        $evaluation = $id === null ? null : Evaluation::find($id);

        Cache::increment($evaluation === null ? self::MISSES : self::HITS);

        return $evaluation;
    }

    /**
     * @return array{hits: int, misses: int}
     */
    public function stats(): array
    {
        return ['hits' => (int) Cache::get(self::HITS, 0), 'misses' => (int) Cache::get(self::MISSES, 0)];
    }

    private function key(int $questionId): string
    {
        return "qa:question:{$questionId}:evaluation";
    }
}
