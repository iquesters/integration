<?php

use Illuminate\Support\Facades\Route;
use Iquesters\Integration\Http\Controllers\IntegrationController;

Route::middleware('web')->group(function () {
    Route::prefix('integrations')->name('integration.')->group(function () {
        Route::get('/', [IntegrationController::class, 'index'])->name('index');
    });
});