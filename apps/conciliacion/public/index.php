<?php

declare(strict_types=1);

use Conciliacion\App;

require __DIR__.'/../vendor/autoload.php';

App::crear(App::contenedor())->run();
