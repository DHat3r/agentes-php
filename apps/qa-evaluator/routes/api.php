<?php

use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TrafficController;
use Illuminate\Support\Facades\Route;

// Laravel agrega el prefijo /api; el gateway nginx agrega /qa delante.
Route::get('/questions', [QuestionController::class, 'index']);
Route::apiResource('evaluations', EvaluationController::class)->only(['index', 'store', 'show']);
Route::post('/traffic', [TrafficController::class, 'store']);
Route::get('/stats', StatsController::class);
