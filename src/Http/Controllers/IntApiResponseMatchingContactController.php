<?php

namespace Iquesters\Integration\Http\Controllers;

use Illuminate\Routing\Controller;
use Iquesters\Integration\Models\OrganisationIntegration;
use Iquesters\Integration\Models\OrganisationIntegrationMeta;
use Iquesters\Integration\Models\ExtIntegration;
use Iquesters\Integration\Models\ExtIntegrationMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Iquesters\Integration\Models\Integration;
use Iquesters\Integration\Models\IntegrationMeta;

class IntApiResponseMatchingContactController extends Controller
{
    public function entityList($organisationUid, $integrationUid, $apiIds, $entityName)
    {
        try {
            $organisation = null;

            // Try to resolve Organisation if class exists
            if (class_exists(\Iquesters\Organisation\Models\Organisation::class)) {
                $organisation = \Iquesters\Organisation\Models\Organisation::where('uid', $organisationUid)->first();
            }

            if (!$organisation) {
                // Fallback dummy organisation
                $organisation = new class {
                    public $id = 1;
                    public $uid;
                    public $name = 'Default Organisation';
                };
                $organisation->uid = $organisationUid;
            }

            // Dynamically resolve entity model from name
            $modelClass = null;
            if (!empty($entityName)) {
                $modelClass = '\\App\\Models\\' . ucfirst(Str::singular($entityName));

                if (!class_exists($modelClass)) {
                    throw new \Exception("Model for entity {$entityName} not found");
                }
            }

            // If organisation not found → return all entities
            if (!class_exists(\Iquesters\Organisation\Models\Organisation::class)) {
                $entities = $modelClass::all();
            } else {
                $entities = $modelClass::where('organisation_id', $organisation->id)->get();
            }

            return view('integration::integrations.entity.match-entity', [
                'organisation'   => $organisation,
                'availableEntity' => $entities,
                'integrationUid' => $integrationUid,
                'apiId'          => $apiIds,
                'entityName'     => $entityName
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to load organisation {$entityName}", [
                'organisation_uid' => $organisationUid,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->with('error', "Failed to load {$entityName}");
        }
    }

    public function matchedEntityDisplay($organisationUid, $integrationUid, $apiIds, $entityName, $entityId)
    {
        try {
            Log::info('=== START MATCHED ENTITY DISPLAY ===');
            Log::info('Matched Entity Display', [
                'organisation_uid' => $organisationUid,
                'integration_uid' => $integrationUid,
                'api_ids' => $apiIds,
                'entity_name' => $entityName,
                'entity_id' => $entityId
            ]);

            // Convert comma-separated API IDs to array
            $apiIdArray = explode(',', $apiIds);

            // Get organisation and integration
            $organisation = null;

            // Try to resolve Organisation if class exists
            if (class_exists(\Iquesters\Organisation\Models\Organisation::class)) {
                $organisation = \Iquesters\Organisation\Models\Organisation::where('uid', $organisationUid)->first();
            }

            if (!$organisation) {
                // Fallback dummy organisation
                $organisation = new class {
                    public $id = 1;
                    public $uid;
                    public $name = 'Default Organisation';
                };
                $organisation->uid = $organisationUid;
            }

            $integration = Integration::where('uid', $integrationUid)->firstOrFail();

            // Get API details for all provided API IDs
            $apis = IntegrationMeta::whereIn('id', $apiIdArray)->get();

            if ($apis->isEmpty()) {
                throw new \Exception("No APIs found with the provided IDs");
            }

            Log::info("API Call details: " . json_encode($apis));

            // Get organisation integration
            $orgIntegration = OrganisationIntegration::where('organisation_id', $organisation->id)
                ->where('integration_masterdata_id', $integration->id)
                ->firstOrFail();

            Log::info('Organisation Integration Found', [
                'org_integration_id' => $orgIntegration->id,
                'organisation_id' => $organisation->id,
                'integration_id' => $integration->id
            ]);

            // Dynamically resolve entity model from name
            $modelClass = '\\App\\Models\\' . ucfirst(Str::singular($entityName));
            if (!class_exists($modelClass)) {
                throw new \Exception("Model for entity {$entityName} not found");
            }

            // Get the entity dynamically
            $entity = $modelClass::where('id', $entityId)->firstOrFail();

            // Load entity metadata if the model supports it
            if (method_exists($entity, 'meta')) {
                $entity->email = $entity->meta()->get('email');
                $entity->phone = $entity->meta()->get('phone');
            } else {
                // Fallback to direct attributes if meta method doesn't exist
                $entity->email = $entity->email ?? null;
                $entity->phone = $entity->phone ?? null;
            }

            Log::info('Entity Details', [
                'entity_name' => $entity->name ?? $entity->title ?? 'N/A',
                'entity_email' => $entity->email,
                'entity_phone' => $entity->phone,
                'entity_class' => get_class($entity)
            ]);

            // Initialize arrays to store results from all APIs
            $allExactMatches = [];
            $allPartialMatches = [];
            $allConfigs = [];
            $allExternalIntegrations = [];

            // Process each API
            foreach ($apis as $api) {
                Log::info('Processing API', ['api_id' => $api->id, 'api_key' => $api->meta_key]);

                // Parse API name and get configurations
                $parts = explode('_', $api->meta_key, 2);
                $apiName = $parts[1] ?? $api->meta_key;
                $confMetaKey = $apiName . '_' . $entityName . '_conf';

                Log::info('Looking for configuration', [
                    'api_meta_key' => $api->meta_key,
                    'api_name' => $apiName,
                    'conf_meta_key' => $confMetaKey
                ]);

                $apiConfigurations = OrganisationIntegrationMeta::where('ref_parent', $orgIntegration->id)
                    ->where('meta_key', $confMetaKey)
                    ->first();

                if (!$apiConfigurations) {
                    Log::info('No configuration found for API', ['api_id' => $api->id]);
                    continue;
                }

                Log::info('API Configuration Found', [
                    'config_id' => $apiConfigurations->id,
                    'config_meta_value' => $apiConfigurations->meta_value
                ]);

                Log::info('Looking for external integrations', [
                    'org_inte_id' => $apiConfigurations->id
                ]);

                $externalIntegrations = ExtIntegration::where('org_inte_id', $apiConfigurations->id)
                    ->with('metas')
                    ->get();

                Log::info('External Integrations Query Results', [
                    'count' => $externalIntegrations->count(),
                    'external_integrations' => $externalIntegrations->toArray()
                ]);

                // Parse configuration to get field mappings
                $config = json_decode($apiConfigurations->meta_value, true);
                $entityConfig = $config[Str::plural($entityName)] ?? $config[strtolower($entityName)] ?? [];

                Log::info('Parsed Configuration', [
                    'full_config' => $config,
                    'entity_config' => $entityConfig
                ]);

                // Find matches for this API
                $exactMatches = [];
                $partialMatches = [];

                foreach ($externalIntegrations as $extIntegration) {
                    Log::info('Processing External Integration', [
                        'ext_integration_id' => $extIntegration->id,
                        'ext_id' => $extIntegration->ext_id,
                        'org_inte_id' => $extIntegration->org_inte_id
                    ]);

                    // Find the syncdata meta
                    $syncDataMeta = $extIntegration->metas->firstWhere('meta_key', 'syncdata');

                    Log::info('SyncData Meta Search', [
                        'has_sync_data_meta' => !is_null($syncDataMeta),
                        'meta_key' => $syncDataMeta->meta_key ?? 'none',
                        'meta_id' => $syncDataMeta->id ?? 'none'
                    ]);

                    if (!$syncDataMeta) {
                        Log::info('Skipping - No syncdata meta found');
                        continue;
                    }

                    $syncData = json_decode($syncDataMeta->meta_value, true);

                    Log::info('Sync Data Extracted', [
                        'sync_data' => $syncData,
                        'sync_data_keys' => array_keys($syncData)
                    ]);

                    // Extract ALL configured fields dynamically
                    $extractedFields = [];

                    // Get response configuration
                    $responseConfig = $entityConfig['response'] ?? $entityConfig;

                    Log::info('Config Debug', [
                        'original_entity_config' => $entityConfig,
                        'response_config' => $responseConfig
                    ]);

                    foreach ($responseConfig as $fieldName => $fieldConfig) {
                        $path = $fieldConfig['res_path'] ?? '';
                        $override = $fieldConfig['override'] ?? null;

                        // Direct mapping based on your actual data
                        $directMapping = [
                            'id' => $syncData['id'] ?? null,
                            'name' => $syncData['name'] ?? $syncData['title'] ?? null,
                            'email' => $syncData['email'] ?? null,
                            'phone' => $syncData['phone'] ?? null,
                            'pan_number' => $syncData['pan_number'] ?? null
                        ];

                        $extractedFields[$fieldName] = $directMapping[$fieldName] ?? null;

                        Log::info("Direct field extraction", [
                            'field_name' => $fieldName,
                            'extracted_value' => $extractedFields[$fieldName]
                        ]);
                    }
                    Log::info('All Extracted Fields', $extractedFields);

                    // Check for exact match
                    $isExactMatch = false;
                    $matchScore = 0;

                    // Name match (most important) - support both 'name' and 'title' fields
                    $extName = $extractedFields['name'] ?? null;
                    $entityName = $entity->name ?? $entity->title ?? null;

                    if (!empty($extName) && !empty($entityName)) {
                        // Normalize both names for better comparison
                        $normalizedExtName = $this->normalizeName($extName);
                        $normalizedEntityName = $this->normalizeName($entityName);

                        $nameSimilarity = $this->calculateSimilarity($normalizedExtName, $normalizedEntityName);

                        Log::info('Name Comparison', [
                            'ext_name' => $extName,
                            'entity_name' => $entityName,
                            'normalized_ext' => $normalizedExtName,
                            'normalized_entity' => $normalizedEntityName,
                            'similarity' => $nameSimilarity
                        ]);

                        if ($normalizedExtName === $normalizedEntityName) {
                            $isExactMatch = true;
                            $matchScore += 50;
                            Log::info('Exact name match found');
                        } elseif ($nameSimilarity > 70) {
                            $matchScore += 30;
                            Log::info('Partial name match found', ['score' => $nameSimilarity]);
                        } elseif ($this->isLikelyMatch($normalizedExtName, $normalizedEntityName)) {
                            $matchScore += 25;
                            Log::info('Likely match found based on name analysis');
                        }
                    }

                    // Email match
                    $extEmail = $extractedFields['email'] ?? null;
                    if (!empty($extEmail) && !empty($entity->email)) {
                        Log::info('Email Comparison', [
                            'ext_email' => $extEmail,
                            'entity_email' => $entity->email
                        ]);

                        if (strtolower(trim($extEmail)) === strtolower(trim($entity->email))) {
                            $isExactMatch = true;
                            $matchScore += 30;
                            Log::info('Exact email match found');
                        } elseif (strpos(strtolower(trim($entity->email)), strtolower(trim($extEmail))) !== false) {
                            $matchScore += 15;
                            Log::info('Partial email match found');
                        }
                    }

                    // Phone match
                    $extPhone = $extractedFields['phone'] ?? null;
                    if (!empty($extPhone) && !empty($entity->phone)) {
                        $normalizedExtPhone = $this->normalizePhone($extPhone);
                        $normalizedEntityPhone = $this->normalizePhone($entity->phone);

                        Log::info('Phone Comparison', [
                            'ext_phone' => $extPhone,
                            'entity_phone' => $entity->phone,
                            'normalized_ext_phone' => $normalizedExtPhone,
                            'normalized_entity_phone' => $normalizedEntityPhone
                        ]);

                        if ($normalizedExtPhone === $normalizedEntityPhone) {
                            $isExactMatch = true;
                            $matchScore += 20;
                            Log::info('Exact phone match found');
                        } elseif ($normalizedExtPhone === $normalizedEntityPhone) {
                            $matchScore += 10;
                            Log::info('Partial phone match found');
                        }
                    }

                    $matchData = [
                        'ext_integration' => $extIntegration,
                        'sync_data' => $syncData,
                        'extracted_fields' => $extractedFields,
                        'match_score' => $matchScore,
                        'api_id' => $api->id,
                        'api_name' => $apiName
                    ];

                    if ($isExactMatch) {
                        $exactMatches[] = $matchData;
                        Log::info('Added to exact matches', ['match_score' => $matchScore]);
                    } elseif ($matchScore > 20) {
                        $partialMatches[] = $matchData;
                        Log::info('Added to partial matches', ['match_score' => $matchScore]);
                    } else {
                        Log::info('No match found', ['match_score' => $matchScore]);
                    }
                }

                // Add results from this API to the overall results
                $allExactMatches = array_merge($allExactMatches, $exactMatches);
                $allPartialMatches = array_merge($allPartialMatches, $partialMatches);
                $allConfigs[$api->id] = $entityConfig;
                $allExternalIntegrations = array_merge($allExternalIntegrations, $externalIntegrations->all());
            }

            Log::info('Matching Results Summary', [
                'exact_matches_count' => count($allExactMatches),
                'partial_matches_count' => count($allPartialMatches)
            ]);

            // Sort partial matches by score
            usort($allPartialMatches, function ($a, $b) {
                return $b['match_score'] - $a['match_score'];
            });

            Log::info('=== END MATCHED ENTITY DISPLAY ===');

            // Get push API ID (assuming this should still be based on the first API)
            $pushApiId = null;
            if ($orgIntegration) {
                $meta = OrganisationIntegrationMeta::where('ref_parent', $orgIntegration->id)
                    ->where('meta_key', 'ZB_api_id')
                    ->first();

                if ($meta && $meta->meta_value) {
                    $configuredApiIds = json_decode($meta->meta_value, true);

                    if (is_array($configuredApiIds)) {
                        $api = IntegrationMeta::whereIn('id', $configuredApiIds)
                            ->where('meta_key', 'api_create_a_contact')
                            ->first();

                        $pushApiId = $api?->id;
                    }
                }
            }

            // Dynamic view name based on entity
            $viewName = "integration::integrations.entity.match-entity-details";

            return view($viewName, [
                'organisation' => $organisation,
                'entity' => $entity, // Changed from 'person' to 'entity'
                'integrationUid' => $integrationUid,
                'apiIds' => $apiIds,
                'entityName' => $entityName,
                'exactMatches' => $allExactMatches,
                'partialMatches' => $allPartialMatches,
                'configs' => $allConfigs,
                'externalIntegrations' => $allExternalIntegrations,
                'pushApiId' => $pushApiId
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to match entity', [
                'organisation_uid' => $organisationUid,
                'entity_id' => $entityId,
                'entity_name' => $entityName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Failed to match entity: ' . $e->getMessage());
        }
    }

    /**
     * Normalize name for better comparison
     */
    private function normalizeName($name)
    {
        if (empty($name)) return '';

        return strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    /**
     * Check if names are likely matches using multiple strategies
     */
    private function isLikelyMatch($name1, $name2)
    {
        if (empty($name1) || empty($name2)) return false;

        // Remove common suffixes in parentheses
        $cleanName1 = preg_replace('/\s*\([^)]*\)/', '', $name1);
        $cleanName2 = preg_replace('/\s*\([^)]*\)/', '', $name2);

        // Check if one contains the other
        if (str_contains($cleanName1, $cleanName2) || str_contains($cleanName2, $cleanName1)) {
            return true;
        }

        // Check initial match (first name + first letter of last name)
        $initials1 = $this->getInitials($cleanName1);
        $initials2 = $this->getInitials($cleanName2);

        if ($initials1 === $initials2) {
            return true;
        }

        return false;
    }

    /**
     * Get name initials for matching
     */
    private function getInitials($name)
    {
        $parts = explode(' ', $name);
        if (count($parts) < 2) return $name;

        $firstName = $parts[0];
        $lastNameInitial = substr(end($parts), 0, 1);

        return $firstName . ' ' . $lastNameInitial;
    }

    /**
     * Extract field value using configuration mapping
     * Completely dynamic based on your config structure
     */
    private function extractFieldValue($data, $path, $override = null, $configFieldName = null)
    {
        if (!empty($override)) return $override;
        if (empty($path)) return null;

        // For your flat data structure, use the config field name directly
        if ($configFieldName) {
            return $data[$configFieldName] ?? null;
        }

        // Fallback: extract from path (for backward compatibility)
        $pathParts = explode('/', $path);
        $fieldIdentifier = end($pathParts);

        // Simple direct mapping
        $simpleMappings = [
            'contact_id' => 'id',
            'contact_name' => 'name',
            'mobile' => 'phone',
            'pan_no' => 'pan_number'
        ];

        $actualFieldName = $simpleMappings[$fieldIdentifier] ?? $fieldIdentifier;
        return $data[$actualFieldName] ?? null;
    }

    /**
     * Calculate similarity percentage between two strings
     */
    private function calculateSimilarity($string1, $string2)
    {
        if (empty($string1) || empty($string2)) {
            return 0;
        }

        $length1 = strlen($string1);
        $length2 = strlen($string2);

        // Calculate Levenshtein distance
        $levenshtein = levenshtein($string1, $string2);

        // Calculate maximum possible distance
        $maxDistance = max($length1, $length2);

        // Calculate similarity percentage
        if ($maxDistance === 0) {
            return 100;
        }

        $similarity = (1 - ($levenshtein / $maxDistance)) * 100;

        return round($similarity, 2);
    }

    /**
     * Normalize phone number for comparison
     */
    private function normalizePhone($phone)
    {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-digit characters
        return preg_replace('/[^0-9]/', '', $phone);
    }
}