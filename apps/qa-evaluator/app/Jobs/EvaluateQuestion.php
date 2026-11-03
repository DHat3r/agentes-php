<?php

namespace App\Jobs;

use Agentes\Core\Exceptions\MaxIterationsReached;
use Agentes\Core\Step;
use App\Agent\QaAgent;
use App\Models\Evaluation;
use App\Support\EvaluationCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Ejecuta el agente para una evaluación, en segundo plano.
 *
 * Reemplaza a Kafka + Flink del proyecto original: si el modelo falla (LlmException),
 * la excepción escapa y Laravel reintenta el job hasta 3 veces, esperando cada vez más.
 */
class EvaluateQuestion implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> Segundos de espera antes de cada reintento (backoff). */
    public array $backoff = [10, 30, 60];

    public function __construct(public Evaluation $evaluation) {}

    public function handle(QaAgent $agent, EvaluationCache $cache): void
    {
        // Idempotencia: si un intento anterior alcanzó a guardar, no se repite el trabajo.
        if ($this->evaluation->status === Evaluation::COMPLETED) {
            return;
        }

        $this->evaluation->update(['status' => Evaluation::RUNNING, 'error' => null]);

        try {
            $result = $agent->evaluate($this->evaluation);
        } catch (MaxIterationsReached $e) {
            // Reintentar no ayudaría: el modelo se quedó dando vueltas.
            $this->evaluation->fail($e->getMessage(), array_map(fn (Step $step) => $step->toArray(), $e->steps));

            return;
        }

        $this->evaluation->refresh();
        if ($this->evaluation->status !== Evaluation::COMPLETED) {
            $this->evaluation->fail('El agente terminó sin guardar un resultado.', $result->trace());

            return;
        }

        $this->evaluation->update(['trace' => $result->trace()]);
        $cache->remember($this->evaluation);
    }

    /** Laravel lo llama cuando se agotan los reintentos. */
    public function failed(?Throwable $exception): void
    {
        $this->evaluation->fail($exception?->getMessage() ?? 'Error desconocido.');
    }
}
