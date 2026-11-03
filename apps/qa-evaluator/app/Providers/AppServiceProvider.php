<?php

namespace App\Providers;

use Agentes\Core\Llm\LlmClient;
use Agentes\Core\Llm\OpenRouterClient;
use App\Agent\AnswerJudge;
use App\Agent\QaAgent;
use App\Support\EvaluationCache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El agente recibe la interfaz; aquí se decide qué implementación usar.
        // Los tests reemplazan este binding por FakeLlmClient.
        $this->app->singleton(LlmClient::class, fn () => $this->openRouter('model'));

        // Binding contextual: el juez también pide un LlmClient, pero con otro modelo.
        $this->app->when(AnswerJudge::class)
            ->needs(LlmClient::class)
            ->give(fn () => $this->openRouter('judge_model'));

        // Parámetros escalares que el contenedor no puede adivinar se leen de la configuración.
        $this->app->when(QaAgent::class)
            ->needs('$maxIterations')
            ->giveConfig('services.openrouter.max_iterations');

        $this->app->when(EvaluationCache::class)
            ->needs('$ttlSeconds')
            ->giveConfig('cache.evaluation_ttl');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    private function openRouter(string $modelKey): OpenRouterClient
    {
        $config = config('services.openrouter');

        return new OpenRouterClient($config['key'], $config[$modelKey], timeoutSeconds: $config['timeout']);
    }
}
