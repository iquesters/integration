<?php

use Illuminate\Support\Facades\Route;
use Iquesters\Integration\Http\Controllers\ApiConfigurationController;
use Iquesters\Integration\Http\Controllers\IntegrationController;
use Iquesters\Integration\Http\Controllers\IntApiCallContactController;
use Iquesters\Integration\Http\Controllers\IntApiResponseMatchingContactController;

Route::middleware('web')->group(function () {
    Route::prefix('Organisation')->name('organisations.')->group(function () {
        Route::prefix('{organisationUid}')->group(function () {
            Route::prefix('integrations')->name('integration.')->group(function () {
                Route::get('/', [IntegrationController::class, 'index'])->name('index');
                Route::post('/{integrationId}/toggle', [IntegrationController::class, 'toggleIntegration'])->name('toggle');
                Route::get('/{integrationUid}/show', [IntegrationController::class, 'showZohoBooks'])->name('show');
                Route::get('/{integrationUid}/data', [IntegrationController::class, 'showZohoBooksData'])->name('data');

                Route::post('/{integrationUid}/store', [IntegrationController::class, 'saveZohoBooksIntegration'])->name('zoho-books.store');
                Route::post('/{integrationUid}/regenerate-access-token', [IntegrationController::class, 'regenerateAccessToken'])->name('zoho-books.regenerate-access-token');
                Route::post('/{integrationUid}/get-tokens', [IntegrationController::class, 'getTokens'])->name('zoho-books.tokens');
                Route::post('/zoho-books/{integrationUid}/save-api-name', [IntegrationController::class, 'saveApiName'])->name('save-api-name');
                Route::post('/{integrationUid}/save-entity-configuration', [IntegrationController::class, 'saveEntityConfiguration'])->name('save-entity-configuration');
                Route::get('/{integrationUid}/api/{apiId}/configure', [ApiConfigurationController::class, 'apiConfigure'])->name('api.configure');
                Route::post('/{integrationUid}/api/{apiId}/save-configuration', [ApiConfigurationController::class, 'saveFieldMappings'])->name('api.save-configuration');
                Route::post('/{integrationUid}/api/{apiId}/{entityName}/api-call', [IntApiCallContactController::class, 'apiCall'])->name('api.api-call');

                Route::get('/{integrationUid}/api/{apiIds}/{entityName}/org-entity-list', [IntApiResponseMatchingContactController::class, 'entityList'])->name('api.entity-list');
            });
        });
    });
});