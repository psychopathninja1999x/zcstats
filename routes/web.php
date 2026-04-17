<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

Route::get('/export.json', [DashboardController::class, 'export'])->name('dashboard.export');
Route::get('/live-digest.json', [DashboardController::class, 'liveDigest'])->name('dashboard.live_digest');

Route::get('/push/vapid-public-key', [PushSubscriptionController::class, 'vapidPublicKey'])->name('push.vapid');
Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
Route::patch('/push/subscribe', [PushSubscriptionController::class, 'update'])->name('push.update');
Route::delete('/push/subscribe', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');

Route::get('/', [DashboardController::class, 'index']);
