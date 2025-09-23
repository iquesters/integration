<?php

namespace Iquesters\Integration\Http\Controllers;

use Illuminate\Routing\Controller;
use Iquesters\Integration\Models\OrganisationIntegration;
use Iquesters\Integration\Models\OrganisationIntegrationMeta;
use Iquesters\Integration\Models\ExtIntegration;
use Iquesters\Integration\Models\ExtIntegrationMeta;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Iquesters\Integration\Models\Integration;
use Iquesters\Integration\Models\IntegrationMeta;

class IntApiCallContactController extends Controller
{
    public function apiCall($organisationUid, $integrationUid, $apiId, $entityName)
    {
        // Remove execution time limit completely
        set_time_limit(0);
        
        // Increase memory limit to handle large data
        ini_set('memory_limit', '512M');
        
        $startTime = time();
        Log::info("API call process started at: " . date('Y-m-d H:i:s'));
        Log::info("API Call initiated for Organisation: $organisationUid, Integration: $integrationUid, API ID: $apiId, Entity: $entityName");

        $organisation = null;
        if (class_exists(\Iquesters\Organisation\Models\Organisation::class)) {
            $organisation = \Iquesters\Organisation\Models\Organisation::where('uid', $organisationUid)->firstOrFail();
        }

        if (!$organisation) {
            // fallback dummy organisation
            $organisation = new class {
                public $id = 1;
                public $uid;
                public $name = 'Default Organisation';
            };
            $organisation->uid = $organisationUid;
        }
        
        $integration = Integration::where('uid', $integrationUid)->firstOrFail();
        $shortName = $integration->small_name ?? '_';

        // Get API details
        $api = IntegrationMeta::where('id', $apiId)->firstOrFail();
        Log::info("API Call details: " . json_encode($api));

        // Get organisation integration
        $orgIntegration = OrganisationIntegration::where('organisation_id', $organisation->id)
            ->where('integration_masterdata_id', $integration->id)
            ->firstOrFail();

        // Parse API name and get configurations
        $parts = explode('_', $api->meta_key, 2);
        $apiName = $parts[1] ?? $api->meta_key;
        $confMetaKey = $apiName . '_' . $entityName . '_conf';

        $apiConfigurations = OrganisationIntegrationMeta::where('ref_parent', $orgIntegration->id)
            ->where('meta_key', $confMetaKey)
            ->firstOrFail();

        Log::info("API Configurations: " . json_encode($apiConfigurations));

        // Get access token and organisation ID from meta
        $accessTokenMeta = OrganisationIntegrationMeta::where('ref_parent', $orgIntegration->id)
            ->where('meta_key', $shortName . '_access_token')
            ->firstOrFail();

        $orgIdMeta = OrganisationIntegrationMeta::where('ref_parent', $orgIntegration->id)
            ->where('meta_key', $shortName . '_organisation_id')
            ->firstOrFail();

        $accessToken = $accessTokenMeta->meta_value;
        $organisationId = $orgIdMeta->meta_value;

        // Check if token is expired (Zoho tokens expire in 1 hour)
        $tokenUpdatedAt = $accessTokenMeta->updated_at;
        $isTokenExpired = $tokenUpdatedAt->diffInMinutes(now()) >= 55; // 5-minute buffer

        // If token is expired, refresh it
        if ($isTokenExpired) {
            try {
                $accessToken = $this->refreshAccessToken($organisation, $orgIntegration, $shortName);
                Log::info("Access token refreshed successfully");
            } catch (\Exception $e) {
                Log::error("Failed to refresh access token: " . $e->getMessage());
                return redirect()->back()->with('error', 'Access token expired and could not be refreshed: ' . $e->getMessage());
            }
        }

        // Parse API configuration
        $apiConfig = json_decode($api->meta_value, true);
        $mappingConfig = json_decode($apiConfigurations->meta_value, true);

        // Prepare API request
        $url = $apiConfig['url'];
        $method = strtoupper($apiConfig['method']);
        $headers = $this->replacePlaceholders($apiConfig['headers'], ['access_token' => $accessToken]);

        // Fetch all pages of data
        $allContacts = [];
        $page = 1;
        $hasMorePages = true;
        $totalRecords = 0;

        try {
            while ($hasMorePages) {
                // Progress logging
                if ($page % 10 === 0) {
                    $elapsedTime = time() - $startTime;
                    Log::info("Progress: Page $page, Total records: $totalRecords, Elapsed time: {$elapsedTime}s");
                }

                // Prepare query parameters for current page
                $queryParams = $this->prepareQueryParams($apiConfig['query_params'], [
                    'organization_id' => $organisationId,
                    'page' => $page,
                    'per_page' => 200 // ZohoBooks allows max 200 per page
                ]);

                Log::info("Fetching page $page:", [
                    'url' => $url,
                    'method' => $method,
                    'headers' => $headers,
                    'query_params' => $queryParams,
                    'page' => $page
                ]);

                // Make API call for current page
                $response = Http::withHeaders($headers)
                    ->timeout(120) // Increased timeout to 2 minutes per request
                    ->{$method}($url, $queryParams);

                if ($response->successful()) {
                    $responseData = $response->json();

                    // Check if we have contacts in this page
                    if (isset($responseData['contacts']) && is_array($responseData['contacts'])) {
                        $pageContacts = $responseData['contacts'];
                        $allContacts = array_merge($allContacts, $pageContacts);
                        $totalRecords += count($pageContacts);

                        Log::info("Page $page fetched: " . count($pageContacts) . " contacts, Total so far: " . $totalRecords);

                        // Check if there are more pages
                        $hasMorePages = $this->hasMorePages($responseData, $pageContacts, $page);
                        $page++;

                        // Add a small delay to avoid rate limiting
                        if ($hasMorePages) {
                            sleep(1); // 1 second delay between requests
                        }
                    } else {
                        Log::warning("No contacts found in page $page");
                        $hasMorePages = false;
                    }
                } else {
                    // Check if the error is due to token expiration
                    $responseBody = $response->json();
                    if ($this->isTokenExpiredError($responseBody)) {
                        Log::warning("Token expired during API call, attempting to refresh");

                        // Refresh token and retry
                        try {
                            $accessToken = $this->refreshAccessToken($organisation, $orgIntegration, $shortName);
                            $headers = $this->replacePlaceholders($apiConfig['headers'], ['access_token' => $accessToken]);
                            Log::info("Token refreshed, retrying page $page");
                            continue; // Retry the same page
                        } catch (\Exception $e) {
                            Log::error("Failed to refresh access token during API call: " . $e->getMessage());
                            return redirect()->back()->with('error', 'Access token expired during API call and could not be refreshed: ' . $e->getMessage());
                        }
                    }

                    Log::error("API call failed for page $page: " . $response->body());
                    return redirect()->back()->with('error', 'API call failed: ' . $response->body());
                }
            }

            $totalTime = time() - $startTime;
            Log::info("Total contacts fetched: " . $totalRecords . " in " . $totalTime . " seconds");

            if ($totalRecords > 0) {
                $this->processAndStoreData(['contacts' => $allContacts], $mappingConfig, $apiConfigurations->id, $entityName);
                $totalProcessingTime = time() - $startTime;
                return redirect()->back()->with('success', "Successfully fetched and stored $totalRecords contacts in $totalProcessingTime seconds");
            } else {
                return redirect()->back()->with('warning', 'No contacts found to import');
            }
        } catch (\Exception $e) {
            Log::error("API call exception: " . $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Refresh access token using refresh token
     */
    private function refreshAccessToken($organisation, $orgIntegration, $shortName)
    {
        $userId = Auth::id();

        // Get required meta values
        $clientIdMeta = OrganisationIntegrationMeta::where('ref_parent', $orgIntegration->id)
            ->where('meta_key', $shortName . '_client_id')
            ->firstOrFail();

        $clientSecretMeta = OrganisationIntegrationMeta::where('ref_parent', $orgIntegration->id)
            ->where('meta_key', $shortName . '_client_secret')
            ->firstOrFail();

        $refreshTokenMeta = OrganisationIntegrationMeta::where('ref_parent', $orgIntegration->id)
            ->where('meta_key', $shortName . '_refresh_token')
            ->firstOrFail();

        $clientId = $clientIdMeta->meta_value;
        $clientSecret = $clientSecretMeta->meta_value;
        $refreshToken = $refreshTokenMeta->meta_value;
        $redirectUri = 'https://trackshoot.com/login';

        // Prepare request parameters
        $params = [
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'refresh_token'
        ];

        Log::debug('Attempting Zoho refresh token exchange', [
            'client_id' => substr($clientId, 0, 5) . '...',
            'refresh_token' => substr($refreshToken, 0, 5) . '...',
            'redirect_uri' => $redirectUri
        ]);

        // Make the request to Zoho
        $client = new Client();
        $response = $client->post('https://accounts.zoho.in/oauth/v2/token', [
            'form_params' => $params,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'http_errors' => false,
            'timeout' => 30 // 30 second timeout for token refresh
        ]);

        $responseData = json_decode($response->getBody(), true);

        Log::debug('Zoho refresh token response', [
            'status_code' => $response->getStatusCode(),
            'response' => $responseData
        ]);

        if ($response->getStatusCode() !== 200) {
            $error = $responseData['error'] ?? 'Unknown error occurred';
            throw new \Exception("Zoho API Error ({$response->getStatusCode()}): {$error}");
        }

        if (!isset($responseData['access_token'])) {
            throw new \Exception('Invalid response from Zoho - missing access token');
        }

        $accessToken = $responseData['access_token'];

        // Update the access token in database
        OrganisationIntegrationMeta::updateOrCreate(
            ['ref_parent' => $orgIntegration->id, 'meta_key' => $shortName . '_access_token'],
            [
                'meta_value' => $accessToken,
                'status' => 'active',
                'created_by' => $userId,
                'updated_by' => $userId
            ]
        );

        return $accessToken;
    }

    /**
     * Check if the error response indicates token expiration
     */
    private function isTokenExpiredError($responseBody)
    {
        if (!is_array($responseBody)) {
            return false;
        }

        // Zoho typically returns error codes for token expiration
        $errorCode = $responseBody['code'] ?? null;
        $message = $responseBody['message'] ?? '';

        // Common Zoho token expiration indicators
        $expirationIndicators = [
            'invalid_token',
            'expired_token',
            'access_token_expired',
            'unauthorized',
            'authentication_failure'
        ];

        // Check if error code indicates expiration
        if (in_array($errorCode, [401, 403]) || 
            in_array(strtolower($message), $expirationIndicators)) {
            return true;
        }

        return false;
    }

    /**
     * Check if there are more pages to fetch
     */
    private function hasMorePages($responseData, $pageContacts, $page)
    {
        // Safety check: don't fetch more than 100 pages (20,000 records max)
        if ($page >= 100) {
            Log::warning("Reached maximum page limit (100 pages) for safety");
            return false;
        }

        // Method 1: Check if page has fewer than 200 records (likely last page)
        if (count($pageContacts) < 200) {
            return false;
        }

        // Method 2: Check Zoho Books specific pagination indicators
        if (isset($responseData['page_context'])) {
            $pageContext = $responseData['page_context'];
            if (isset($pageContext['has_more_page'])) {
                return (bool) $pageContext['has_more_page'];
            }
        }

        // Method 3: Check if current page has any records
        return count($pageContacts) > 0;
    }

    /**
     * Prepare query parameters with proper type casting
     */
    private function prepareQueryParams($queryParamsConfig, $replacements)
    {
        $result = [];

        foreach ($queryParamsConfig as $key => $valueType) {
            // Handle replacements first
            if (isset($replacements[$key])) {
                $result[$key] = $replacements[$key];
                continue;
            }

            // Handle special cases for pagination parameters
            if ($key === 'per_page') {
                $result[$key] = 200; // ZohoBooks allows max 200 per page
                continue;
            }

            if ($key === 'page') {
                $result[$key] = 1; // Default to page 1
                continue;
            }

            // Handle other parameters based on their type definition
            if ($valueType === 'number') {
                $result[$key] = 0; // Default numeric value
            } elseif ($valueType === 'string') {
                $result[$key] = ''; // Default string value
            } else {
                $result[$key] = $valueType; // Keep original value for unknown types
            }
        }

        return $result;
    }

    /**
     * Replace placeholders in configuration with actual values (for headers only)
     */
    private function replacePlaceholders($config, $replacements)
    {
        $result = [];

        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->replacePlaceholders($value, $replacements);
            } else {
                $result[$key] = str_replace(
                    array_map(fn($k) => '{' . $k . '}', array_keys($replacements)),
                    array_values($replacements),
                    $value
                );
            }
        }

        return $result;
    }

    /**
     * Process API response and store data in database
     */
    private function processAndStoreData($responseData, $mappingConfig, $orgIntegrationMetaId, $entityName)
    {
        if (!isset($responseData['contacts']) || !is_array($responseData['contacts'])) {
            Log::warning("No contacts found in response");
            return;
        }

        // Check if the new structure exists (with response section)
        if (isset($mappingConfig[$entityName]['response'])) {
            $mappingConfig = $mappingConfig[$entityName]['response'];
        } else {
            // Fallback to old structure for backward compatibility
            $mappingConfig = $mappingConfig[$entityName] ?? [];
            Log::warning("Using old mapping structure - consider updating to new format");
        }

        $totalContacts = count($responseData['contacts']);
        Log::info("Processing $totalContacts contacts for storage");

        $batchSize = 50;
        $extIntegrations = [];
        $extIntegrationMetas = [];
        $currentUserId = Auth::id() ?? 0;

        foreach ($responseData['contacts'] as $index => $contact) {
            try {
                // Extract data based on mapping configuration
                $syncData = [];

                foreach ($mappingConfig as $field => $config) {
                    $path = is_array($config) ? ($config['res_path'] ?? '') : $config;
                    $value = $this->getValueFromPath($contact, $path);
                    $syncData[$field] = is_array($config) ? ($config['override'] ?? $value) : $value;
                }

                $extId = $this->getValueFromPath($contact, 'contact_id');

                if (!$extId) {
                    Log::warning("Skipping contact without ID");
                    continue;
                }

                // Prepare ExtIntegration for batch insert/update
                $extIntegrations[] = [
                    'org_inte_id' => $orgIntegrationMetaId,
                    'ext_id' => $extId,
                    'uid' => \Illuminate\Support\Str::ulid(),
                    'status' => 'active',
                    'created_by' => $currentUserId,
                    'updated_by' => $currentUserId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                // Prepare ExtIntegrationMeta for batch insert/update
                $extIntegrationMetas[] = [
                    'ref_parent' => null, // Will be updated after ExtIntegration insert
                    'meta_key' => 'syncdata',
                    'meta_value' => json_encode($syncData),
                    'status' => 'active',
                    'created_by' => $currentUserId,
                    'updated_by' => $currentUserId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                // Process in smaller batches to avoid timeouts
                if (count($extIntegrations) >= $batchSize) {
                    $this->processBatch($extIntegrations, $extIntegrationMetas, $orgIntegrationMetaId, $currentUserId);
                    $extIntegrations = [];
                    $extIntegrationMetas = [];

                    // Add a small delay between batches
                    usleep(100000); // 0.1 second delay
                }
            } catch (\Exception $e) {
                Log::error("Error processing contact: " . $e->getMessage());
            }
        }

        // Process remaining records
        if (!empty($extIntegrations)) {
            $this->processBatch($extIntegrations, $extIntegrationMetas, $orgIntegrationMetaId, $currentUserId);
        }

        Log::info("Successfully processed contacts batch");
    }

    /**
     * Process batch of records with proper update logic
    */
    private function processBatch($extIntegrations, $extIntegrationMetas, $orgIntegrationMetaId, $currentUserId)
    {
        DB::transaction(function () use ($extIntegrations, $extIntegrationMetas, $orgIntegrationMetaId, $currentUserId) {
            // Get existing records to check what needs to be updated vs inserted
            $extIds = array_column($extIntegrations, 'ext_id');
            
            $existingIntegrations = ExtIntegration::where('org_inte_id', $orgIntegrationMetaId)
                ->whereIn('ext_id', $extIds)
                ->get()
                ->keyBy('ext_id');
                
            $existingIntegrationIds = $existingIntegrations->pluck('id')->toArray();
            
            $existingMetas = ExtIntegrationMeta::whereIn('ref_parent', $existingIntegrationIds)
                ->where('meta_key', 'syncdata')
                ->get()
                ->keyBy('ref_parent');

            // Prepare arrays for upsert
            $integrationsToUpsert = [];
            $metasToUpsert = [];

            foreach ($extIntegrations as $index => $integrationData) {
                $extId = $integrationData['ext_id'];
                
                if (isset($existingIntegrations[$extId])) {
                    // Record exists - update only the necessary fields
                    $existingIntegration = $existingIntegrations[$extId];
                    
                    $integrationsToUpsert[] = [
                        'id' => $existingIntegration->id, // Include ID for update
                        'org_inte_id' => $integrationData['org_inte_id'],
                        'ext_id' => $integrationData['ext_id'],
                        'status' => 'active',
                        'updated_by' => $currentUserId,
                        'updated_at' => now(),
                        // Preserve original values for these fields
                        'uid' => $existingIntegration->uid,
                        'created_by' => $existingIntegration->created_by,
                        'created_at' => $existingIntegration->created_at
                    ];
                    
                    // For meta data, update only meta_value, updated_by, and updated_at
                    if (isset($existingMetas[$existingIntegration->id])) {
                        $existingMeta = $existingMetas[$existingIntegration->id];
                        
                        $metasToUpsert[] = [
                            'id' => $existingMeta->id, // Include ID for update
                            'ref_parent' => $existingIntegration->id,
                            'meta_key' => 'syncdata',
                            'meta_value' => $extIntegrationMetas[$index]['meta_value'], // Update with new data
                            'status' => 'active',
                            'updated_by' => $currentUserId,
                            'updated_at' => now(),
                            // Preserve original values for these fields
                            'created_by' => $existingMeta->created_by,
                            'created_at' => $existingMeta->created_at
                        ];
                    } else {
                        // Meta record doesn't exist but integration does - create new meta
                        $metasToUpsert[] = [
                            'ref_parent' => $existingIntegration->id,
                            'meta_key' => 'syncdata',
                            'meta_value' => $extIntegrationMetas[$index]['meta_value'],
                            'status' => 'active',
                            'created_by' => $currentUserId,
                            'updated_by' => $currentUserId,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                } else {
                    // New record - insert everything
                    $integrationsToUpsert[] = $integrationData;
                    
                    // Store meta data with temporary index reference
                    $metasToUpsert[] = [
                        'temp_index' => $index, // Temporary reference
                        'meta_key' => 'syncdata',
                        'meta_value' => $extIntegrationMetas[$index]['meta_value'],
                        'status' => 'active',
                        'created_by' => $currentUserId,
                        'updated_by' => $currentUserId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }

            // Upsert integrations
            if (!empty($integrationsToUpsert)) {
                ExtIntegration::upsert(
                    $integrationsToUpsert,
                    ['id', 'org_inte_id', 'ext_id'], // Use ID for updates, combination for new records
                    ['status', 'updated_by', 'updated_at']
                );
            }

            // For new integrations, we need to get their IDs to set ref_parent in metas
            $newIntegrationExtIds = [];
            foreach ($integrationsToUpsert as $integration) {
                if (!isset($integration['id'])) { // New records won't have ID
                    $newIntegrationExtIds[] = $integration['ext_id'];
                }
            }

            if (!empty($newIntegrationExtIds)) {
                $newIntegrations = ExtIntegration::where('org_inte_id', $orgIntegrationMetaId)
                    ->whereIn('ext_id', $newIntegrationExtIds)
                    ->get()
                    ->keyBy('ext_id');
            }

            // Prepare final meta data for upsert
            $finalMetasToUpsert = [];
            foreach ($metasToUpsert as $metaData) {
                if (isset($metaData['id'])) {
                    // Existing meta record
                    $finalMetasToUpsert[] = $metaData;
                } elseif (isset($metaData['temp_index'])) {
                    // New meta record for existing integration
                    $tempIndex = $metaData['temp_index'];
                    $extId = $extIntegrations[$tempIndex]['ext_id'];
                    
                    if (isset($newIntegrations[$extId])) {
                        $finalMetasToUpsert[] = [
                            'ref_parent' => $newIntegrations[$extId]->id,
                            'meta_key' => 'syncdata',
                            'meta_value' => $metaData['meta_value'],
                            'status' => 'active',
                            'created_by' => $currentUserId,
                            'updated_by' => $currentUserId,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                } else {
                    // New meta record for new integration (shouldn't happen due to above logic)
                    $finalMetasToUpsert[] = $metaData;
                }
            }

            // Upsert meta data
            if (!empty($finalMetasToUpsert)) {
                ExtIntegrationMeta::upsert(
                    $finalMetasToUpsert,
                    ['id', 'ref_parent', 'meta_key'],
                    ['meta_value', 'status', 'updated_by', 'updated_at']
                );
            }
        });
    }

    /**
     * Get value from nested array using path notation
     * Handles incorrect paths by falling back to direct field access
     */
    private function getValueFromPath($data, $path)
    {
        if (empty($path)) {
            return null;
        }

        // Clean up the path - remove any incorrect prefixes and handle new structure
        $path = str_replace(['contact/', 'contacts/', 'response.', 'request.'], '', $path);

        $pathParts = explode('/', $path);
        $current = $data;

        foreach ($pathParts as $part) {
            if (empty($part)) continue; // Skip empty parts

            if (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } elseif (is_object($current) && isset($current->$part)) {
                $current = $current->$part;
            } else {
                // Try alternative approaches if direct path doesn't work
                if ($part === 'contact_id' && isset($current['contact_id'])) {
                    $current = $current['contact_id'];
                } elseif ($part === 'contact_name' && isset($current['contact_name'])) {
                    $current = $current['contact_name'];
                } elseif ($part === 'company_name' && isset($current['company_name'])) {
                    $current = $current['company_name'];
                } else {
                    return null; // Path part not found
                }
            }
        }

        return $current;
    }
}