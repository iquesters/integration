<?php

use Illuminate\Support\Facades\Route;
use Iquesters\Integration\Http\Controllers\IntegrationController;

Route::middleware('web')->group(function () {
    Route::prefix('Organisation')->name('organisations.')->group(function () {
        Route::prefix('{organisationUid}')->group(function () {
            Route::prefix('integrations')->name('integration.')->group(function () {
                Route::get('/', [IntegrationController::class, 'index'])->name('index');
                Route::post('/{integrationId}/toggle', [IntegrationController::class, 'toggleIntegration'])->name('toggle');
                Route::get('/{integrationUid}/show', [IntegrationController::class, 'showZohoBooks'])->name('show');

                Route::post('/{integrationUid}/store', [IntegrationController::class, 'saveZohoBooksIntegration'])->name('zoho-books.store');
                Route::post('/{integrationUid}/regenerate-access-token', [IntegrationController::class, 'regenerateAccessToken'])->name('zoho-books.regenerate-access-token');
                Route::post('/{integrationUid}/get-tokens', [IntegrationController::class, 'getTokens'])->name('zoho-books.tokens');
                Route::post('/zoho-books/{integrationUid}/save-api-name', [IntegrationController::class, 'saveApiName'])->name('save-api-name');
                Route::get('/{integrationUid}/api/{apiId}/configure', [ApiConfigurationController::class, 'apiConfigure'])->name('api.configure');
            });
        });
    });
});