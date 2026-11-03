<?php

declare(strict_types=1);

use Agentes\Core\Llm\LlmClient;
use Agentes\Core\Llm\OpenRouterClient;
use Conciliacion\Agente\AgenteConciliador;
use Conciliacion\Persistencia\Database;
use Psr\Container\ContainerInterface;

use function DI\autowire;
use function DI\factory;

/*
 * Definiciones de PHP-DI. Las clases sin parámetros escalares (repositorio, regla, lector,
 * controladores) se resuelven solas por autowiring; aquí solo va lo que necesita configuración.
 * En Laravel esto lo hace el AppServiceProvider; en Slim lo escribimos explícito.
 */
return [
    'env' => fn () => getenv(),

    PDO::class => factory(fn (ContainerInterface $c) => Database::conectar($c->get('env'))),

    // Se crea al primer uso: sin OPENROUTER_API_KEY la app arranca igual y el error aparece al conciliar.
    LlmClient::class => factory(fn (ContainerInterface $c) => new OpenRouterClient(
        $c->get('env')['OPENROUTER_API_KEY'] ?? '',
        $c->get('env')['OPENROUTER_MODEL'] ?? 'nvidia/nemotron-3.5-super-vl:free,nvidia/nemotron-3-ultra-550b-a55b:free,nvidia/nemotron-3.5-lightning:free,openrouter/free',
        timeoutSeconds: (float) ($c->get('env')['OPENROUTER_TIMEOUT'] ?? 60),
    )),

    AgenteConciliador::class => autowire()->constructorParameter(
        'maxIteraciones',
        (int) (getenv('AGENT_MAX_ITERATIONS') ?: 15),
    ),
];
