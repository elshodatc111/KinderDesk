<?php

use App\Http\Controllers\Api\BackupController;
use Illuminate\Support\Facades\Route;

/* Tashqi zaxira tizimi uchun (login + parol + IP ro'yxati). Sessiya va CSRF yo'q. */
Route::prefix('backup')->middleware('throttle:backup')->group(function () {
    Route::post('/request', [BackupController::class, 'request'])->name('api.backup.request');
    Route::get('/{uid}/download', [BackupController::class, 'download'])->name('api.backup.download');
});
