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
}