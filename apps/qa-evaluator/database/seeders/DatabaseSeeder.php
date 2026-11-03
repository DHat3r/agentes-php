<?php

namespace Database\Seeders;

use App\Dataset\YahooAnswersImporter;
use App\Models\Question;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Carga la muestra de preguntas solo si la tabla está vacía, para poder
     * correr el seeder en cada arranque del contenedor sin duplicar datos.
     */
    public function run(YahooAnswersImporter $importer): void
    {
        if (Question::query()->exists()) {
            return;
        }

        $importer->import(database_path('data/preguntas_muestra.csv'));
    }
}
