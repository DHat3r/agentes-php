<?php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Un estudiante de demo para probar la app sin crear nada. Sin Faker: en producción
     * no se instalan las dependencias de desarrollo. Se puede correr en cada arranque.
     */
    public function run(): void
    {
        Student::firstOrCreate(['name' => 'Estudiante demo']);
    }
}
