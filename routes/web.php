<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BackgroundJobController;

Route::get('/', [BackgroundJobController::class, 'index'])->name('jobs.index');
Route::post('/cancel', [BackgroundJobController::class, 'cancel'])->name('jobs.cancel');
Route::post('/launch', [BackgroundJobController::class, 'launch'])->name('jobs.launch');