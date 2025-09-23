<?php

namespace Iquesters\Integration\Http\Controllers;

use Illuminate\Routing\Controller;
use Iquesters\Integration\Models\OrganisationIntegration;
use Iquesters\Integration\Models\OrganisationIntegrationMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Iquesters\Integration\Models\Integration;
use Iquesters\Integration\Models\IntegrationMeta;

class ApiConfigurationController extends Controller
{
    public function apiConfigure($organisationUid, $integrationUid, $apiId, Request $request)
    {
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
        
        $zohoBooksIntegration = Integration::where('uid', $integrationUid)->firstOrFail();
        $api = IntegrationMeta::where('id', $apiId)->firstOrFail();

        // Get selected table from request or default to first available entity
        $selectedTable = $request->get('table_name', '');
        
        // Get available entities/tables for dropdown (dynamic from organisation_integration_metas)
        $entities = $this->getAvailableEntities($organisation->id, $zohoBooksIntegration->id);
        
        // If no table selected and entities exist, use the first one
        if (empty($selectedTable)) {
            $selectedTable = !empty($entities) ? $entities[0]['table_name'] : 'persons';
        }

        Log::info('Selected Table: ' . $selectedTable);
        
        // Get project table fields for mapping (excluding unwanted columns)
        $mappableFields = $this->getMappableFields($selectedTable);

        // Parse response schema to get available response fields
        $responseFields = $this->parseResponseSchema($api);

        // Parse body schema to get available body fields
        $bodySchemaData = $this->parseBodySchema($api);
        $requiredBodyFields = $bodySchemaData['required'] ?? [];
        $optionalBodyFields = $bodySchemaData['optional'] ?? [];

        // Get existing mappings if any
        $existingMappings = $this->getExistingMappings($apiId, $organisation->id, $zohoBooksIntegration->id, $selectedTable);
        $existingBodyMappings = $this->getExistingBodyMappings($apiId, $organisation->id, $zohoBooksIntegration->id, $selectedTable);

        // Extract response_schema_id_key if available
        $responseSchemaIdKey = null;
        $bodySchemaIdKey = null;
        try {
            $metaValue = json_decode($api->meta_value, true);
            $responseSchemaIdKey = $metaValue['response_schema_id_key'] ?? null;
            $bodySchemaIdKey = $metaValue['body_schema_id_key'] ?? null;
        } catch (\Exception $e) {
            Log::error('Error parsing schema ID key: ' . $e->getMessage());
        }

        return view('integration::integrations.zoho_books.api-configure', compact(
            'zohoBooksIntegration',
            'api',
            'entities',
            'selectedTable',
            'mappableFields',
            'responseFields',
            'requiredBodyFields',
            'optionalBodyFields',
            'existingMappings',
            'existingBodyMappings',
            'organisation',
            'organisationUid',
            'integrationUid',
            'responseSchemaIdKey',
            'bodySchemaIdKey'
        ));
    }

    /**
     * Get available entities/tables for dropdown (dynamic from organisation_integration_metas)
     */
    private function getAvailableEntities($organisationId, $integrationId)
    {
        try {
            // Get the parent integration record
            $parentIntegration = OrganisationIntegration::where([
                'organisation_id' => $organisationId,
                'integration_masterdata_id' => $integrationId
            ])->first();

            if (!$parentIntegration) {
                return $this->getDefaultEntities();
            }

            // Look for entity configuration in organisation_integration_metas
            $entityMeta = OrganisationIntegrationMeta::where([
                'ref_parent' => $parentIntegration->id,
                'meta_key' => 'entity_configuration'
            ])->first();

            if (!$entityMeta) {
                return $this->getDefaultEntities();
            }

            // Parse the entity configuration
            $metaValue = json_decode($entityMeta->meta_value, true);

            $entityNames = $metaValue['entity_names'] ?? '';
            $defaultEntity = $metaValue['default_entity'] ?? '';

            if (empty($entityNames)) {
                return $this->getDefaultEntities();
            }

            // Split comma-separated entity names
            $entities = array_map('trim', explode(',', $entityNames));

            $availableEntities = [];
            foreach ($entities as $entity) {
                if (!empty($entity)) {
                    $availableEntities[] = [
                        'table_name' => $entity,
                        'display_name' => ucwords(str_replace('_', ' ', $entity))
                    ];
                }
            }

            return !empty($availableEntities) ? $availableEntities : $this->getDefaultEntities();
        } catch (\Exception $e) {
            Log::error('Error getting available entities: ' . $e->getMessage());
            return $this->getDefaultEntities();
        }
    }

    /**
     * Fallback to default entities if dynamic lookup fails
     */
    private function getDefaultEntities()
    {
        return [
            ['table_name' => 'persons', 'display_name' => 'Persons'],
            ['table_name' => 'projects', 'display_name' => 'Projects'],
        ];
    }

    /**
     * Get mappable fields from table (excluding unwanted columns)
     */
    private function getMappableFields($tableName)
    {
        $excludedColumns = ['uid', 'ref_parent', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at'];

        // Get main table fields
        $mainFields = $this->getTableFields($tableName, $excludedColumns);

        // Get meta table fields
        $metaTableName = $this->getMetaTableName($tableName);
        $metaKeys = $this->getUniqueMetaKeys($metaTableName);

        // Combine fields
        $combinedFields = [];

        // Add main table fields
        foreach ($mainFields as $field) {
            $combinedFields[] = [
                'value' => $field['full_path'],
                'display_name' => $field['display_name'],
                'type' => $field['type'],
                'source' => 'main_table'
            ];
        }

        // Add meta keys
        foreach ($metaKeys as $metaKey) {
            $combinedFields[] = [
                'value' => $metaKey['full_path'],
                'display_name' => $metaKey['display_name'],
                'type' => 'meta',
                'source' => 'meta_table'
            ];
        }

        return $combinedFields;
    }

    /**
     * Get table fields (excluding unwanted columns)
     */
    private function getTableFields($tableName, $excludedColumns = [])
    {
        if (!Schema::hasTable($tableName)) {
            return [];
        }

        $columns = Schema::getColumnListing($tableName);
        $fields = [];

        foreach ($columns as $column) {
            if (in_array($column, $excludedColumns)) {
                continue;
            }

            $columnType = Schema::getColumnType($tableName, $column);

            $fields[] = [
                'name' => $column,
                'type' => $columnType,
                'display_name' => ucwords(str_replace('_', ' ', $column)),
                'full_path' => "{$tableName}.{$column}"
            ];
        }

        return $fields;
    }

    /**
     * Get meta table name based on main table name
     */
    private function getMetaTableName($mainTableName)
    {
        // Convert plural to singular and add _metas
        $singular = rtrim($mainTableName, 's');
        return $singular . '_metas';
    }

    /**
     * Get unique meta keys from meta table
     */
    private function getUniqueMetaKeys($metaTableName)
    {
        if (!Schema::hasTable($metaTableName)) {
            return [];
        }

        try {
            $metaKeys = DB::table($metaTableName)
                ->select('meta_key')
                ->distinct()
                ->where('status', '!=', 'deleted')
                ->orderBy('meta_key')
                ->pluck('meta_key')
                ->toArray();

            return array_map(function ($key) use ($metaTableName) {
                return [
                    'key' => $key,
                    'display_name' => ucwords(str_replace(['_', '/'], [' ', ' / '], $key)),
                    'full_path' => "{$metaTableName}.meta_value->{$key}"
                ];
            }, $metaKeys);
        } catch (\Exception $e) {
            Log::error("Error getting unique meta keys from {$metaTableName}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse response schema to extract available fields
     */
    private function parseResponseSchema($api)
    {
        try {
            $metaValue = json_decode($api->meta_value, true);

            if (!isset($metaValue['response_schema'])) {
                return [];
            }

            $responseSchema = $metaValue['response_schema'];
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
     * Get existing field mappings from organisation_integration_metas
     */
    private function getExistingMappings($apiId, $organisationId, $integrationId)
    {
        try {
            // Get the API record to extract API name
            $api = IntegrationMeta::findOrFail($apiId);
            $parts = explode('_', $api->meta_key, 2);
            $apiName = $parts[1] ?? $api->meta_key;

            // Get the parent integration record
            $parentIntegration = OrganisationIntegration::where([
                'organisation_id' => $organisationId,
                'integration_masterdata_id' => $integrationId
            ])->first();

            if (!$parentIntegration) {
                return [];
            }

            // Get the table name from request or use default
            $tableName = request()->get('table_name', 'persons');
            $metaKey = $apiName . '_' . $tableName . '_conf';

            // Get the organisation integration meta record
            $orgIntegrationMeta = OrganisationIntegrationMeta::where([
                'ref_parent' => $parentIntegration->id,
                'meta_key' => $metaKey
            ])->first();

            if (!$orgIntegrationMeta) {
                return [];
            }

            $metaValue = json_decode($orgIntegrationMeta->meta_value, true);
            $mappings = [];

            if (isset($metaValue[$tableName])) {
                foreach ($metaValue[$tableName] as $field => $mappingData) {
                    // Reconstruct the full field path
                    $fullFieldPath = $tableName . '.' . $field;

                    $mappings[$fullFieldPath] = [
                        'project_field' => $fullFieldPath,
                        'project_field_label' => ucwords(str_replace('_', ' ', $field)),
                        'response_field' => $mappingData['res_path'] ?? '',
                    ];
                }
            }

            return $mappings;
        } catch (\Exception $e) {
            Log::error('Error getting existing field mappings: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Parse body schema to extract required and optional fields
     */
    private function parseBodySchema($api)
    {
        try {
            $metaValue = json_decode($api->meta_value, true);

            if (!isset($metaValue['body_schema'])) {
                return ['required' => [], 'optional' => []];
            }

            $bodySchema = $metaValue['body_schema'];
            $requiredKeys = $metaValue['req_schema_required_keys'] ?? [];
            
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
     * Get existing body field mappings
     */
    private function getExistingBodyMappings($apiId, $organisationId, $integrationId, $tableName)
    {
        try {
            // Get the API record to extract API name
            $api = IntegrationMeta::findOrFail($apiId);
            $parts = explode('_', $api->meta_key, 2);
            $apiName = $parts[1] ?? $api->meta_key;

            // Get the parent integration record
            $parentIntegration = OrganisationIntegration::where([
                'organisation_id' => $organisationId,
                'integration_masterdata_id' => $integrationId
            ])->first();

            if (!$parentIntegration) {
                return [];
            }

            $metaKey = $apiName . '_' . $tableName . '_req';

            // Get the organisation integration meta record
            $orgIntegrationMeta = OrganisationIntegrationMeta::where([
                'ref_parent' => $parentIntegration->id,
                'meta_key' => $metaKey
            ])->first();

            if (!$orgIntegrationMeta) {
                return [];
            }

            $metaValue = json_decode($orgIntegrationMeta->meta_value, true);
            $mappings = [];

            if (isset($metaValue[$tableName])) {
                foreach ($metaValue[$tableName] as $field => $mappingData) {
                    $mappings[$field] = [
                        'body_field' => $field,
                        'body_field_label' => ucwords(str_replace(['_', '/'], ' ', $field)),
                        'project_field' => $mappingData['proj_path'] ?? '',
                    ];
                }
            }

            return $mappings;
        } catch (\Exception $e) {
            Log::error('Error getting existing body field mappings: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Save field mappings (both response and body)
     */
    public function saveFieldMappings(Request $request, $organisationUid, $integrationUid, $apiId)
    {
        try {
            $request->validate([
                'api_id' => 'required|exists:integration_metas,id',
                'table_name' => 'required|string',
                'mappings' => 'required|array',
                'body_mappings' => 'array',
            ]);

            $organisation = null;
            if (class_exists(\Iquesters\Organisation\Models\Organisation::class)) {
                $organisation = \Iquesters\Organisation\Models\Organisation::where('uid', $organisationUid)->firstOrFail();
            }

            if (!$organisation) {
                $organisation = new class {
                    public $id = 1;
                    public $uid;
                };
                $organisation->uid = $organisationUid;
            }
            $userId = Auth::id();

            // Get the integration record
            $integration = Integration::where('uid', $integrationUid)->firstOrFail();
            $shortName = $integration->small_name ?? '_';

            // Get the API record
            $api = IntegrationMeta::findOrFail($request->api_id);
            $parts = explode('_', $api->meta_key, 2);
            $apiName = $parts[1] ?? $api->meta_key;

            $tableName = $request->table_name;
            $mappingsData = $request->mappings;
            $bodyMappingsData = $request->body_mappings ?? [];

            // Get or create the parent integration record
            $parentIntegration = OrganisationIntegration::firstOrCreate(
                [
                    'organisation_id' => $organisation->id,
                    'integration_masterdata_id' => $integration->id
                ],
                [
                    'created_by' => $userId,
                    'updated_by' => $userId
                ]
            );

            // Process response mappings
            $responseMappings = [];
            foreach ($mappingsData as $key => $mapping) {
                if (!empty($mapping['response_field'])) {
                    // Extract column name from project_field
                    $parts = explode('.', $mapping['project_field']);
                    $columnName = end($parts);

                    if (str_contains($columnName, '->')) {
                        $columnParts = explode('->', $columnName);
                        $columnName = end($columnParts);
                    }

                    $responseMappings[$columnName] = [
                        'res_path' => $mapping['response_field'],
                        'override' => null
                    ];
                }
            }

            // Process body mappings
            $bodyMappings = [];
            foreach ($bodyMappingsData as $key => $mapping) {
                if (!empty($mapping['project_field'])) {
                    // Extract column name from project_field
                    $parts = explode('.', $mapping['project_field']);
                    $columnName = end($parts);

                    if (str_contains($columnName, '->')) {
                        $columnParts = explode('->', $columnName);
                        $columnName = end($columnParts);
                    }

                    $bodyMappings[$mapping['body_field']] = [
                        'entity_col' => $columnName,
                        'override' => null
                    ];
                }
            }

            // Prepare combined mapping structure
            $combinedMappingStructure = [
                $tableName => [
                    'request' => $bodyMappings,
                    'response' => $responseMappings
                ]
            ];

            $metaValue = json_encode($combinedMappingStructure);
            $metaKey = $apiName . '_' . $tableName . '_conf';

            // Save combined mappings to organisation_integration_metas table
            OrganisationIntegrationMeta::updateOrCreate(
                [
                    'ref_parent' => $parentIntegration->id,
                    'meta_key' => $metaKey
                ],
                [
                    'meta_value' => $metaValue,
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId
                ]
            );

            // Redirect to the integration show page
            return redirect()->route('organisations.integration.show', [
                'organisationUid' => $organisationUid,
                'integrationUid' => $integrationUid
            ])->with('success', 'Field mappings saved successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error saving field mappings: ' . $e->getMessage(), [
                'organisation_uid' => $organisationUid,
                'integration_uid' => $integrationUid,
                'api_id' => $apiId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Error saving field mappings: ' . $e->getMessage());
        }
    }
}