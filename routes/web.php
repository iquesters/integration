<?php

use Illuminate\Support\Facades\Route;
use Iquesters\Integration\Http\Controllers\ApiConfigurationController;
use Iquesters\Integration\Http\Controllers\IntegrationController;
use Iquesters\Integration\Http\Controllers\IntApiCallContactController;
use Iquesters\Integration\Http\Controllers\IntApiResponseMatchingContactController;
use Iquesters\Integration\Http\Controllers\IntegrationConfigController;
use Iquesters\Integration\Http\Controllers\WebsiteController;

Route::middleware(['web','auth'])->group(function () {
    Route::post('/api/fetch-website', [WebsiteController::class, 'fetchWebsite'])->name('fetch.website');
    // Route::prefix('Organisation')->name('organisations.')->group(function () {
        // Route::prefix('{organisationUid}')->group(function () {
            Route::prefix('integrations')->name('integration.')->group(function () {
                Route::get('/', [IntegrationController::class, 'index'])->name('index');
                Route::get('/create', [IntegrationController::class, 'create'])->name('create');
                Route::post('/', [IntegrationController::class, 'store'])->name('store');
                Route::get('/{integrationUid}/edit', [IntegrationController::class, 'edit'])->name('edit');
                Route::put('/{integrationUid}', [IntegrationController::class, 'update'])->name('update');
                Route::delete('/{integrationUid}', [IntegrationController::class, 'destroy'])->name('destroy');
                Route::get('/{integrationUid}', [IntegrationController::class, 'show'])->name('show');
                
                Route::get('/{integrationUid}/configure', [IntegrationConfigController::class, 'configure'])->name('configure');
                Route::post('/save-configuration', [IntegrationConfigController::class, 'store'])->name('configure.store');
            
                Route::post('/{integrationId}/toggle', [IntegrationController::class, 'toggleIntegration'])->name('toggle');
                Route::get('/{integrationUid}/show', [IntegrationController::class, 'showZohoBooks'])->name('show-zoho');
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
                Route::get('/{integrationUid}/api/{apiIds}/{entityName}/{entityId}', [IntApiResponseMatchingContactController::class, 'matchedEntityDisplay'])->name('api.matched-entity-display');
            });
    //     });
    // });
});