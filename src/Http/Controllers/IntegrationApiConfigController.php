<?php

namespace Iquesters\Integration\Http\Controllers;

use Illuminate\Routing\Controller;
use Iquesters\Integration\Models\OrganisationIntegration;
use Iquesters\Integration\Models\OrganisationIntegrationMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Iquesters\Foundation\Models\Entity;
use Iquesters\Foundation\Models\EntityMeta;
use Iquesters\Integration\Models\Integration;
use Iquesters\Integration\Models\IntegrationMeta;

class IntegrationApiConfigController extends Controller
{
    public function apiconf($integrationUid)
    {
        try {
            $integration = Integration::where('uid', $integrationUid)
                ->with(['metas', 'supportedIntegration.metas'])
                ->firstOrFail();

            $provider = $integration->supportedIntegration;

            // 1. All ACTIVE APIs defined by provider (api_*)
            $apis = $provider->metas()
                ->where('meta_key', 'like', 'api_%')
                ->where('status', 'active')
                ->get();

            // 2. Dynamic meta key: {small_name}_api_id
            $apiMetaKey = $provider->small_name . '_api_id';

            // 3. Fetch selected API IDs
            $selectedApiMeta = $integration->metas()
                ->where('meta_key', $apiMetaKey)
                ->first();

            $selectedApiIds = $selectedApiMeta
                ? json_decode($selectedApiMeta->meta_value, true)
                : [];

            // 4. Selected API objects
            $selectedApis = $apis->whereIn('id', $selectedApiIds);

            Log::debug('Integration API Config', [
                'integration_uid' => $integrationUid,
                'provider'        => $provider->name,
                'api_meta_key'    => $apiMetaKey,
                'available_apis'  => $apis->pluck('meta_key'),
                'selected_apis'   => $selectedApis->pluck('meta_key'),
            ]);

            return view(
                'integration::integrations.woocommerces.apiconf',
                compact(
                    'integration',
                    'apis',
                    'selectedApis',
                    'selectedApiIds'
                )
            );

        } catch (\Throwable $th) {
            Log::error('Integration apiconf Error', [
                'integration_uid' => $integrationUid,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', $th->getMessage());
        }
    }
    
    public function saveapiconf(Request $request, $integrationUid)
    {
        try {
            $integration = Integration::where('uid', $integrationUid)
                ->with('supportedIntegration.metas')
                ->firstOrFail();

            $provider = $integration->supportedIntegration;

            // Dynamic meta key: {small_name}_api_id
            $apiMetaKey = $provider->small_name . '_api_id';

            // Selected API IDs from request
            $selectedApiIds = $request->input('selected_metas', []);

            // Ensure array
            if (!is_array($selectedApiIds)) {
                $selectedApiIds = [];
            }

            // Valid ACTIVE provider API IDs
            $validApiIds = $provider->metas()
                ->where('meta_key', 'like', 'api_%')
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();

            // Keep only valid API IDs
            $selectedApiIds = array_values(array_intersect(
                $validApiIds,
                $selectedApiIds
            ));

            // Save / Update integration meta
            IntegrationMeta::updateOrCreate(
                [
                    'ref_parent' => $integration->id,
                    'meta_key'   => $apiMetaKey,
                ],
                [
                    'meta_value' => json_encode($selectedApiIds),
                    'status'     => 'active',
                    'created_by' => Auth::id() ?? 0,
                    'updated_by' => Auth::id() ?? 0,
                ]
            );

            Log::info('Integration API Config Saved', [
                'integration_uid' => $integrationUid,
                'api_meta_key'    => $apiMetaKey,
                'selected_api_ids'=> $selectedApiIds,
            ]);

            return redirect()
                ->back()
                ->with('success', 'API configuration saved successfully.');

        } catch (\Throwable $th) {
            Log::error('Integration saveapiconf Error', [
                'integration_uid' => $integrationUid,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', $th->getMessage());
        }
    }
    
    public function apiConfigure($integrationUid, $apiId, Request $request)
    {
        try {
            $integration = Integration::where('uid', $integrationUid)
                ->with(['metas', 'supportedIntegration.metas'])
                ->firstOrFail();

            $provider = $integration->supportedIntegration;

            // Get selected API IDs
            $apiMetaKey = $provider->small_name . '_api_id';
            $selectedApiMeta = $integration->metas
                ->where('meta_key', $apiMetaKey)
                ->first();

            $selectedApiIds = $selectedApiMeta
                ? json_decode($selectedApiMeta->meta_value, true)
                : [];

            if (!is_array($selectedApiIds)) {
                $selectedApiIds = [];
            }

            // Verify API is selected
            if (!in_array((int) $apiId, $selectedApiIds, true)) {
                throw new \Exception('API is not enabled for this integration.');
            }

            // Load API definition
            $apiMeta = $provider->metas()
                ->where('id', $apiId)
                ->where('meta_key', 'like', 'api_%')
                ->where('status', 'active')
                ->first();

            if (!$apiMeta) {
                throw new \Exception('API definition not found or inactive.');
            }

            $apiConfig = json_decode($apiMeta->meta_value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid API configuration JSON.');
            }

            // Get selected entity from request or default
            $selectedEntityId = $request->get('entity_id');
            
            // Get available entities for dropdown
            $entities = $this->getAvailableEntities();
            
            // Get entity details if one is selected
            $selectedEntity = null;
            $mappableFields = [];
            
            if ($selectedEntityId) {
                $selectedEntity = Entity::find($selectedEntityId);
                if ($selectedEntity) {
                    $mappableFields = $this->getMappableFields($selectedEntity);
                }
            }

            // Parse response schema to get available response fields
            $responseFields = $this->parseResponseSchema($apiConfig);

            // Parse body schema to get available body fields
            $bodySchemaData = $this->parseBodySchema($apiConfig);
            $requiredBodyFields = $bodySchemaData['required'] ?? [];
            $optionalBodyFields = $bodySchemaData['optional'] ?? [];

            // Get existing mappings if entity is selected
            $existingMappings = [];
            $existingBodyMappings = [];
            
            if ($selectedEntity) {
                $existingMappings = $this->getExistingMappings(
                    $apiId, 
                    $integration->id, 
                    $selectedEntity->id
                );
                $existingBodyMappings = $this->getExistingBodyMappings(
                    $apiId, 
                    $integration->id, 
                    $selectedEntity->id
                );
            }

            // Extract schema ID keys
            $responseSchemaIdKey = $apiConfig['response_schema_id_key'] ?? null;
            $bodySchemaIdKey = $apiConfig['body_schema_id_key'] ?? null;

            return view(
                'integration::integrations.woocommerces.api-configure',
                compact(
                    'integration',
                    'provider',
                    'apiMeta',
                    'apiConfig',
                    'entities',
                    'selectedEntity',
                    'mappableFields',
                    'responseFields',
                    'requiredBodyFields',
                    'optionalBodyFields',
                    'existingMappings',
                    'existingBodyMappings',
                    'responseSchemaIdKey',
                    'bodySchemaIdKey'
                )
            );

        } catch (\Throwable $th) {
            Log::error('Integration apiConfigure Error', [
                'integration_uid' => $integrationUid,
                'api_id' => $apiId,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', $th->getMessage());
        }
    }

    /**
     * Get available entities for dropdown
     */
    private function getAvailableEntities()
    {
        return Entity::where('status', '!=', 'deleted')
            ->orderBy('entity_name')
            ->get()
            ->map(function ($entity) {
                return [
                    'id' => $entity->id,
                    'name' => $entity->entity_name,
                    'display_name' => ucwords(str_replace('_', ' ', $entity->entity_name))
                ];
            })
            ->toArray();
    }

    /**
     * Get mappable fields from entity (main fields + meta fields)
     */
    private function getMappableFields(Entity $entity)
    {
        $combinedFields = [];

        // Add main entity fields from the 'fields' JSON column
        // Check if fields is already an array or needs decoding
        $mainFields = is_string($entity->fields) 
            ? json_decode($entity->fields, true) 
            : $entity->fields;
        
        $mainFields = $mainFields ?? [];
        
        foreach ($mainFields as $field) {
            $fieldName = is_array($field) ? ($field['name'] ?? '') : $field;
            if (!empty($fieldName)) {
                $combinedFields[] = [
                    'value' => "entity.{$fieldName}",
                    'display_name' => ucwords(str_replace('_', ' ', $fieldName)),
                    'type' => is_array($field) ? ($field['type'] ?? 'string') : 'string',
                    'source' => 'main_entity'
                ];
            }
        }

        // Add meta fields from entity_metas table
        $metaKeys = $this->getUniqueMetaKeys($entity->id);
        foreach ($metaKeys as $metaKey) {
            $combinedFields[] = [
                'value' => $metaKey['full_path'],
                'display_name' => $metaKey['display_name'],
                'type' => 'meta',
                'source' => 'entity_meta'
            ];
        }

        return $combinedFields;
    }

    /**
     * Get unique meta keys for an entity from meta_fields column
     */
    private function getUniqueMetaKeys($entityId)
    {
        try {
            $entity = Entity::find($entityId);
            
            if (!$entity) {
                return [];
            }
            
            // Get meta fields from the entity's meta_fields column
            // Check if meta_fields is already an array or needs decoding
            $metaFields = is_string($entity->meta_fields) 
                ? json_decode($entity->meta_fields, true) 
                : $entity->meta_fields;
            
            $metaFields = $metaFields ?? [];
            
            // Convert the meta_fields object to an array of meta key info
            $metaKeys = [];
            foreach ($metaFields as $key => $fieldInfo) {
                $metaKeys[] = [
                    'key' => $key,
                    'display_name' => $fieldInfo['label'] ?? ucwords(str_replace(['_', '/'], [' ', ' / '], $key)),
                    'full_path' => "entity_meta.{$key}",
                    'type' => $fieldInfo['type'] ?? 'string',
                    'required' => $fieldInfo['required'] ?? false,
                ];
            }
            
            return $metaKeys;
        } catch (\Exception $e) {
            Log::error("Error getting unique meta keys for entity {$entityId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse response schema to extract available fields
     */
    private function parseResponseSchema($apiConfig)
    {
        try {
            if (!isset($apiConfig['response_schema'])) {
                return [];
            }

            $responseSchema = $apiConfig['response_schema'];
            $fieldPaths = [];

            $this->extractFieldPaths($responseSchema, '', $fieldPaths);
            sort($fieldPaths);

            return $fieldPaths;
        } catch (\Exception $e) {
            Log::error('Error parsing response schema: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse body schema to extract required and optional fields
     */
    private function parseBodySchema($apiConfig)
    {
        try {
            if (!isset($apiConfig['body_schema'])) {
                return ['required' => [], 'optional' => []];
            }

            $bodySchema = $apiConfig['body_schema'];
            $requiredKeys = $apiConfig['req_schema_required_keys'] ?? [];
            
            $requiredFields = [];
            $optionalFields = [];
            
            $this->extractBodyFieldPaths($bodySchema, '', $requiredFields, $optionalFields, $requiredKeys);
            
            sort($requiredFields);
            sort($optionalFields);

            return [
                'required' => $requiredFields,
                'optional' => $optionalFields
            ];
        } catch (\Exception $e) {
            Log::error('Error parsing body schema: ' . $e->getMessage());
            return ['required' => [], 'optional' => []];
        }
    }

    /**
     * Recursively extract field paths from response schema
     */
    private function extractFieldPaths($schema, $currentPath = '', &$fieldPaths = [], $maxDepth = 10)
    {
        if ($maxDepth <= 0) return;

        if (!is_array($schema) && !is_object($schema)) {
            if ($currentPath) {
                $fieldPaths[] = $currentPath;
            }
            return;
        }

        if (is_array($schema) && isset($schema[0]) && is_array($schema[0])) {
            $this->extractFieldPaths($schema[0], $currentPath, $fieldPaths, $maxDepth - 1);
            return;
        }

        foreach ($schema as $key => $value) {
            $newPath = $currentPath ? "{$currentPath}/{$key}" : $key;

            if (is_array($value) || is_object($value)) {
                $this->extractFieldPaths($value, $newPath, $fieldPaths, $maxDepth - 1);
            } else {
                $fieldPaths[] = $newPath;
            }
        }
    }

    /**
     * Recursively extract body field paths from body schema
     */
    private function extractBodyFieldPaths($schema, $currentPath = '', &$requiredFields = [], &$optionalFields = [], $requiredKeys = [], $maxDepth = 10)
    {
        if ($maxDepth <= 0) return;

        if (!is_array($schema) && !is_object($schema)) {
            if ($currentPath) {
                $isRequired = in_array($currentPath, $requiredKeys);
                if ($isRequired) {
                    $requiredFields[] = $currentPath;
                } else {
                    $optionalFields[] = $currentPath;
                }
            }
            return;
        }

        if (is_array($schema) && isset($schema[0]) && is_array($schema[0])) {
            $this->extractBodyFieldPaths($schema[0], $currentPath, $requiredFields, $optionalFields, $requiredKeys, $maxDepth - 1);
            return;
        }

        foreach ($schema as $key => $value) {
            $newPath = $currentPath ? "{$currentPath}/{$key}" : $key;

            if (is_array($value) || is_object($value)) {
                $this->extractBodyFieldPaths($value, $newPath, $requiredFields, $optionalFields, $requiredKeys, $maxDepth - 1);
            } else {
                $isRequired = in_array($newPath, $requiredKeys);
                if ($isRequired) {
                    $requiredFields[] = $newPath;
                } else {
                    $optionalFields[] = $newPath;
                }
            }
        }
    }

    /**
     * Get existing response field mappings
     */
    private function getExistingMappings($apiId, $integrationId, $entityId)
    {
        try {
            // Get API meta to extract API name from supported_integration_metas
            $apiMeta = \Iquesters\Integration\Models\SupportedIntegrationMeta::findOrFail($apiId);
            $parts = explode('_', $apiMeta->meta_key, 2);
            $apiName = $parts[1] ?? $apiMeta->meta_key;

            // Get entity to extract entity name
            $entity = Entity::findOrFail($entityId);
            $entityName = $entity->entity_name;

            $metaKey = $apiName . '_' . $entityName . '_conf';

            // Get the combined mapping from integration metas
            $mappingMeta = IntegrationMeta::where('ref_parent', $integrationId)
                ->where('meta_key', $metaKey)
                ->first();

            if (!$mappingMeta) {
                return [];
            }

            $metaValue = json_decode($mappingMeta->meta_value, true);
            $mappings = [];

            // Extract response mappings from the combined structure
            if (isset($metaValue[$entityName]['response'])) {
                foreach ($metaValue[$entityName]['response'] as $field => $mappingData) {
                    $fullFieldPath = strpos($field, '.') !== false ? $field : "entity.{$field}";
                    
                    $mappings[$fullFieldPath] = [
                        'entity_field' => $fullFieldPath,
                        'entity_field_label' => ucwords(str_replace(['_', '.'], [' ', ' '], $field)),
                        'response_field' => $mappingData['res_path'] ?? '',
                    ];
                }
            }

            return $mappings;
        } catch (\Exception $e) {
            Log::error('Error getting existing response mappings: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get existing body field mappings
     */
    private function getExistingBodyMappings($apiId, $integrationId, $entityId)
    {
        try {
            // Get API meta to extract API name from supported_integration_metas
            $apiMeta = \Iquesters\Integration\Models\SupportedIntegrationMeta::findOrFail($apiId);
            $parts = explode('_', $apiMeta->meta_key, 2);
            $apiName = $parts[1] ?? $apiMeta->meta_key;

            // Get entity to extract entity name
            $entity = Entity::findOrFail($entityId);
            $entityName = $entity->entity_name;

            $metaKey = $apiName . '_' . $entityName . '_conf';

            // Get the combined mapping from integration metas
            $mappingMeta = IntegrationMeta::where('ref_parent', $integrationId)
                ->where('meta_key', $metaKey)
                ->first();

            if (!$mappingMeta) {
                return [];
            }

            $metaValue = json_decode($mappingMeta->meta_value, true);
            $mappings = [];

            // Extract request mappings from the combined structure
            if (isset($metaValue[$entityName]['request'])) {
                foreach ($metaValue[$entityName]['request'] as $bodyField => $mappingData) {
                    $projPath = $mappingData['proj_path'] ?? '';
                    $fullFieldPath = strpos($projPath, '.') !== false ? $projPath : "entity.{$projPath}";
                    
                    $mappings[$bodyField] = [
                        'body_field' => $bodyField,
                        'body_field_label' => ucwords(str_replace(['_', '/'], ' ', $bodyField)),
                        'entity_field' => $fullFieldPath,
                    ];
                }
            }

            return $mappings;
        } catch (\Exception $e) {
            Log::error('Error getting existing body mappings: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Save field mappings (both response and body)
     */
    public function saveFieldMappings(Request $request, $integrationUid, $apiId)
    {
        Log::info('=== Save Field Mappings Started ===', [
            'integration_uid' => $integrationUid,
            'api_id' => $apiId,
            'request_data' => $request->all(),
        ]);

        try {
            $request->validate([
                'api_id' => 'required|exists:supported_integration_metas,id',
                'entity_id' => 'required|exists:entities,id',
                'mappings' => 'required|array',
                'body_mappings' => 'array',
            ]);

            Log::info('Validation passed');

            $integration = Integration::where('uid', $integrationUid)->firstOrFail();
            $userId = Auth::id();

            Log::info('Integration found', [
                'integration_id' => $integration->id,
                'integration_name' => $integration->name,
                'user_id' => $userId,
            ]);

            // Get the API record from supported_integration_metas (provider metas)
            $apiMeta = \Iquesters\Integration\Models\SupportedIntegrationMeta::findOrFail($request->api_id);
            $parts = explode('_', $apiMeta->meta_key, 2);
            $apiName = $parts[1] ?? $apiMeta->meta_key;

            Log::info('API Meta found', [
                'api_meta_id' => $apiMeta->id,
                'api_meta_key' => $apiMeta->meta_key,
                'api_name' => $apiName,
            ]);

            $entityId = $request->entity_id;
            $mappingsData = $request->mappings;
            $bodyMappingsData = $request->body_mappings ?? [];

            // Get entity to extract entity name
            $entity = Entity::findOrFail($entityId);
            $entityName = $entity->entity_name; // Use entity_name column

            Log::info('Processing mappings', [
                'entity_id' => $entityId,
                'entity_name' => $entityName,
                'mappings_count' => count($mappingsData),
                'body_mappings_count' => count($bodyMappingsData),
            ]);

            // Process response mappings
            $responseMappings = [];
            foreach ($mappingsData as $key => $mapping) {
                if (!empty($mapping['response_field'])) {
                    // Extract field name from entity_field
                    $parts = explode('.', $mapping['entity_field']);
                    $fieldName = end($parts);

                    $responseMappings[$fieldName] = [
                        'res_path' => $mapping['response_field'],
                        'override' => null
                    ];

                    Log::debug('Response mapping processed', [
                        'entity_field' => $mapping['entity_field'],
                        'field_name' => $fieldName,
                        'response_field' => $mapping['response_field'],
                    ]);
                }
            }

            Log::info('Response mappings processed', [
                'total_response_mappings' => count($responseMappings),
                'response_mappings' => $responseMappings,
            ]);

            // Process body mappings (request)
            $bodyMappings = [];
            foreach ($bodyMappingsData as $key => $mapping) {
                if (!empty($mapping['entity_field'])) {
                    // Extract field name from entity_field
                    $parts = explode('.', $mapping['entity_field']);
                    $fieldName = end($parts);

                    $bodyMappings[$mapping['body_field']] = [
                        'proj_path' => $fieldName,
                        'override' => null
                    ];

                    Log::debug('Body mapping processed', [
                        'body_field' => $mapping['body_field'],
                        'entity_field' => $mapping['entity_field'],
                        'field_name' => $fieldName,
                    ]);
                }
            }

            Log::info('Body mappings processed', [
                'total_body_mappings' => count($bodyMappings),
                'body_mappings' => $bodyMappings,
            ]);

            // Combine mappings in the required structure
            $combinedMappings = [
                $entityName => [
                    'request' => $bodyMappings,
                    'response' => $responseMappings
                ]
            ];

            // Save combined mappings with meta_key format: {api_name}_{entity_name}_conf
            $metaKey = $apiName . '_' . $entityName . '_conf';
            
            $mappingRecord = IntegrationMeta::updateOrCreate(
                [
                    'ref_parent' => $integration->id,
                    'meta_key' => $metaKey
                ],
                [
                    'meta_value' => json_encode($combinedMappings),
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId
                ]
            );

            Log::info('Combined mappings saved', [
                'meta_key' => $metaKey,
                'meta_id' => $mappingRecord->id,
                'was_recently_created' => $mappingRecord->wasRecentlyCreated,
                'structure' => $combinedMappings,
            ]);

            Log::info('=== Save Field Mappings Completed Successfully ===', [
                'integration_uid' => $integrationUid,
                'entity_id' => $entityId,
                'entity_name' => $entityName,
                'meta_key' => $metaKey,
            ]);

            return redirect()
                ->route('integration.show', ['integrationUid' => $integrationUid])
                ->with('success', 'Field mappings saved successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed', [
                'errors' => $e->errors(),
                'input' => $request->all(),
            ]);
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('=== Save Field Mappings Failed ===', [
                'integration_uid' => $integrationUid,
                'api_id' => $apiId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Error saving field mappings: ' . $e->getMessage());
        }
    }

}