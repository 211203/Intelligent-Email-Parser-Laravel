<?php

use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

Route::post('/process', [BookingController::class, 'process']);
Route::post('/test-pdf', [BookingController::class, 'testPdf']);
