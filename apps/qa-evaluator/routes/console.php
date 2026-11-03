<?php

use App\Dataset\YahooAnswersImporter;
use Illuminate\Support\Facades\Artisan;

Artisan::command('qa:importar {archivo} {--limite=1000}', function (YahooAnswersImporter $importer) {
    $total = $importer->import($this->argument('archivo'), (int) $this->option('limite'));
    $this->info("Se importaron {$total} preguntas.");
})->purpose('Importa preguntas desde un CSV con el formato de Yahoo Answers (Kaggle)');
