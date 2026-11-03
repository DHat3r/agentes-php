<?php

namespace App\Providers;

use Agentes\Core\Llm\LlmClient;
use Agentes\Core\Llm\OpenRouterClient;
use App\Tutor\TutorAgent;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Mismo patrón que el evaluador QA: el agente pide la interfaz y los tests inyectan FakeLlmClient.
        $this->app->singleton(LlmClient::class, function () {
            $config = config('services.openrouter');

            return new OpenRouterClient($config['key'], $config['model'], timeoutSeconds: $config['timeout']);
        });

        $this->app->when(TutorAgent::class)
            ->needs('$maxIterations')
            ->giveConfig('services.openrouter.max_iterations');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();
    }
}
