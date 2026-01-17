<?php

namespace Iquesters\Integration\Http\Controllers;

use Illuminate\Routing\Controller;
use Iquesters\Integration\Models\OrganisationIntegration;
use Iquesters\Integration\Models\OrganisationIntegrationMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

}