<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProcessEmailController;
use App\Http\Controllers\SimpleProcessController;

Route::get('/', function () {
    return view('welcome');
});

// Email processing endpoints (complex - may have dependency issues)
Route::post('/process', [ProcessEmailController::class, 'process']);
Route::post('/process/content', [ProcessEmailController::class, 'processContent']);
Route::get('/process/status', [ProcessEmailController::class, 'status']);
Route::get('/process/test', [ProcessEmailController::class, 'test']);

// Simple processing endpoints (no dependencies)
Route::post('/simple', [SimpleProcessController::class, 'process']);
Route::get('/simple/status', [SimpleProcessController::class, 'status']);
