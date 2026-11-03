<?php

declare(strict_types=1);

namespace Conciliacion;

use Conciliacion\Http\ConciliacionController;
use Conciliacion\Http\EjemplosController;
use Conciliacion\Http\ManejadorErrores;
use DI\Container;
use DI\ContainerBuilder;
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;

/**
 * Arma la aplicación Slim. Los tests llaman a crear() con un contenedor modificado
 * (SQLite en memoria y FakeLlmClient), y public/index.php con el de producción.
 */
final class App
{
    public static function contenedor(): Container
    {
        return (new ContainerBuilder)->addDefinitions(__DIR__.'/../config/container.php')->build();
    }

    public static function crear(Container $contenedor): SlimApp
    {
        $app = AppFactory::createFromContainer($contenedor);

        $app->get('/api/conciliaciones', [ConciliacionController::class, 'listar']);
        $app->post('/api/conciliaciones', [ConciliacionController::class, 'crear']);
        $app->get('/api/conciliaciones/{id:[0-9]+}', [ConciliacionController::class, 'ver']);
        $app->get('/api/ejemplos/{tipo}', [EjemplosController::class, 'descargar']);

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(false, true, false)
            ->setDefaultErrorHandler(new ManejadorErrores($app->getResponseFactory()));

        return $app;
    }
}
