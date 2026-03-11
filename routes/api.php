<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\ProcessEmailController;
use App\Http\Controllers\SimpleProcessController;
use App\Http\Controllers\GmailFirstController;
use Illuminate\Support\Facades\Route;

// Original booking routes
Route::post('/process', [BookingController::class, 'process']);
Route::post('/test-pdf', [BookingController::class, 'testPdf']);

// New email processing routes
Route::post('/email/process', [ProcessEmailController::class, 'process']);
Route::post('/email/content', [ProcessEmailController::class, 'processContent']);
Route::get('/email/status', [ProcessEmailController::class, 'status']);
Route::get('/email/test', [ProcessEmailController::class, 'test']);

// Simple processing routes (recommended)
Route::post('/simple', [SimpleProcessController::class, 'process']);
Route::get('/simple/status', [SimpleProcessController::class, 'status']);

// Gmail-first processing routes (what you requested)
Route::post('/gmail-first', [GmailFirstController::class, 'process']);
Route::get('/gmail-first/status', [GmailFirstController::class, 'status']);

// Gmail authentication routes (NEW - Real Gmail API)
Route::get('/gmail/auth', [GmailFirstController::class, 'authUrl']);
Route::get('/gmail/callback', [GmailFirstController::class, 'callback']);
