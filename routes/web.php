<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

Route::get('/export.json', [DashboardController::class, 'export'])->name('dashboard.export');
Route::get('/', [DashboardController::class, 'index']);
