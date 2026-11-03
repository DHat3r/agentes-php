<?php

use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

// Laravel agrega el prefijo /api; el gateway nginx agrega /tutor delante.
Route::get('/topics', [StudentController::class, 'topics']);
Route::get('/students', [StudentController::class, 'index']);
Route::post('/students', [StudentController::class, 'store']);
Route::get('/students/{student}/progress', [StudentController::class, 'progress']);
Route::post('/students/{student}/exercises', [ExerciseController::class, 'store']);
Route::post('/exercises/{exercise}/answer', [ExerciseController::class, 'answer']);
