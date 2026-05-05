<?php

namespace Iquesters\Integration\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Iquesters\Integration\Models\Integration;
use Illuminate\Support\Facades\Log;
use Iquesters\Integration\Constants\Constants;
use Iquesters\Integration\Jobs\SyncVectorJob;
use Iquesters\Integration\Models\IntegrationMeta;

class IntegrationConfigController extends Controller
{
    public function configure($integrationUid)
    {
        try {
            $integration = Integration::where('uid', $integrationUid)
                ->with('metas')
                ->firstOrFail();

            $provider = $integration->supportedIntegration;

            Log::debug('Integration Configure', [
                'integration_uid' => $integrationUid,
                'provider' => $provider->name,
            ]);

            // Get existing configuration data
            $websiteUrl = $integration->getMeta('website_url');
            $consumerKey = $integration->getMeta('consumer_key');
            $consumerSecret = $integration->getMeta('consumer_secret');
            $isActive = $integration->getMeta('is_active');
            $chatbot_vector = $integration->getMeta('chatbot_vector');
            $humanHandoverEnabled = (string) $integration->getMeta('human_handover_enabled', 'false');

            Log::info('Loading Integration Configuration', [
                'integration_uid' => $integrationUid,
                'has_website_url' => !empty($websiteUrl),
                'has_consumer_key' => !empty($consumerKey),
                'has_consumer_secret' => !empty($consumerSecret),
                'is_active' => $isActive,
                'human_handover_enabled' => $humanHandoverEnabled,
            ]);

            switch ($provider->name) {
                case Constants::WOOCOMMERCE:
                    return view(
                        'integration::integrations.woocommerces.configure',
                        compact(
                            'integration',
                            'websiteUrl',
                            'consumerKey',
                            'consumerSecret',
                            'isActive'
                        )
                    );
                case Constants::GAUTAMS_CHATBOT:
                    return view(
                        'integration::integrations.gautams_bot.configure',
                        compact(
                            'integration',
                            'chatbot_vector',
                            'humanHandoverEnabled'
                        )
                    );
                default:
                    abort(404, 'Integration provider not supported.');
            }
        } catch (\Throwable $th) {
            Log::error('Integration Configure Error', [
                'integration_uid' => $integrationUid,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', $th->getMessage());
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'url'             => 'required|url',
            'consumer_key'    => 'required|string',
            'consumer_secret' => 'required|string',
        ]);

        try {
            $integration = Integration::where('user_id', auth()->id())
                ->whereHas('supportedIntegration', function ($q) {
                    $q->where('name', 'woocommerce');
                })
                ->firstOrFail();

            $userId = auth()->id();

            $this->saveIntegrationMeta($integration->id, 'website_url', $request->url, $userId);
            $this->saveIntegrationMeta($integration->id, 'consumer_key', $request->consumer_key, $userId);
            $this->saveIntegrationMeta($integration->id, 'consumer_secret', $request->consumer_secret, $userId);

            $integration->update([
                'status'     => 'active',
                'updated_by' => $userId,
            ]);
            $provider = $integration->supportedIntegration;
            
            $payload = [
                'integration_id' => $integration->id,
                'systems' => [
                    [
                        'integration_provider' => $provider->name,
                        'integration_uuid'     => $integration->uid,
                        'recreate_flag'        => false,
                    ]
                ],
            ];
            
            SyncVectorJob::dispatch($payload);
            
            return response()->json([
                'success'  => true,
                'redirect' => route('integration.show', $integration->uid),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to save integration configuration.',
            ], 500);
        }
    }

    public function saveGautamsBotConfiguration(Request $request, $integrationUid)
    {
        try {
            $integration = Integration::where('uid', $integrationUid)
                ->whereHas('supportedIntegration', function ($query) {
                    $query->where('name', Constants::GAUTAMS_CHATBOT);
                })
                ->firstOrFail();

            $userId = auth()->id() ?? 0;
            $enabled = filter_var(
                $request->input('human_handover_enabled', 'false'),
                FILTER_VALIDATE_BOOLEAN
            ) ? 'true' : 'false';

            $this->saveIntegrationMeta(
                $integration->id,
                'human_handover_enabled',
                $enabled,
                $userId
            );

            return redirect()
                ->route('integration.configure', ['integrationUid' => $integrationUid])
                ->with('success', 'Gautams Chatbot configuration updated successfully.');
        } catch (\Throwable $e) {
            Log::error('Gautams Chatbot configuration save failed', [
                'integration_uid' => $integrationUid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to update Gautams Chatbot configuration.');
        }
    }

    public function knob($integrationUid)
    {
        try {
            $integration = Integration::where('uid', $integrationUid)
                ->with(['metas', 'supportedIntegration'])
                ->firstOrFail();

            $provider = $integration->supportedIntegration;

            Log::debug('Integration Knob', [
                'integration_uid' => $integrationUid,
                'provider' => $provider->name
            ]);

            $knobTypes = ['gc_vec_knob', 'gc_chatbot_knob'];
            $defaultKnobType = match ($provider->name) {
                Constants::WOOCOMMERCE => 'gc_vec_knob',
                Constants::GAUTAMS_CHATBOT => 'gc_chatbot_knob',
                default => $knobTypes[0],
            };
            $knobStatus = 'unknown';

            return view('integration::integrations.knob', [
                'integration' => $integration,
                'integrationUid' => $integrationUid,
                'knobTypes' => $knobTypes,
                'defaultKnobType' => $defaultKnobType,
                'knobStatus' => $knobStatus,
            ]);
        } catch (\Throwable $th) {
            Log::error('Integration knob Error', [
                'integration_uid' => $integrationUid,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function knobData(Request $request, $integrationUid)
    {
        try {
            $knobType = $request->query('knob_type');
            $url = "https://api-util.iquesters.com/v1/knobs/{$integrationUid}";

            if (!empty($knobType)) {
                $url .= '/' . urlencode($knobType) . '/all';
            }

            $response = Http::acceptJson()->timeout(20)->get($url);

            if (!$response->successful()) {
                $payload = $response->json();

                Log::warning('Knob API request failed', [
                    'integration_uid' => $integrationUid,
                    'status' => $response->status(),
                    'url' => $url,
                    'response' => $payload,
                ]);

                return response()->json(
                    is_array($payload) ? $payload : ['message' => 'Unable to fetch knob data from upstream service.'],
                    $response->status()
                );
            }

            return response()->json($response->json());
        } catch (\Throwable $th) {
            Log::error('Knob data proxy error', [
                'integration_uid' => $integrationUid,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch knob data.',
            ], 500);
        }
    }

    public function knobActivate(Request $request, $integrationUid)
    {
        try {
            $request->validate([
                'knob_type' => 'required|string',
                'version' => 'required',
            ]);

            $knobType = $request->input('knob_type');
            $version = $request->input('version');
            $url = "https://api-util.iquesters.com/v1/knobs/{$integrationUid}/" . urlencode($knobType) . '/' . urlencode((string) $version) . '/activate';

            $response = Http::acceptJson()->post($url);

            if (!$response->successful()) {
                $payload = $response->json();

                Log::warning('Knob activate API request failed', [
                    'integration_uid' => $integrationUid,
                    'knob_type' => $knobType,
                    'version' => $version,
                    'status' => $response->status(),
                    'url' => $url,
                    'response' => $payload,
                ]);

                return response()->json(
                    is_array($payload) ? $payload : ['message' => 'Unable to activate knob version.'],
                    $response->status()
                );
            }

            return response()->json($response->json());
        } catch (\Throwable $th) {
            Log::error('Knob activate proxy error', [
                'integration_uid' => $integrationUid,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to activate knob version.',
            ], 500);
        }
    }

    public function knobSave(Request $request, $integrationUid)
    {
        try {
            $knobType = (string) $request->query('knob_type', $request->input('knob_type', ''));
            $yaml = (string) $request->getContent();

            Log::debug('Knob Save Request', [
                'integration_uid' => $integrationUid,
                'knob_type' => $knobType,
                'payload_length' => strlen($yaml),
                'yaml_payload' => $yaml
            ]);
            
            if ($knobType === '') {
                return response()->json([
                    'message' => 'Knob type is required.',
                ], 422);
            }

            if (trim($yaml) === '') {
                return response()->json([
                    'message' => 'Knob YAML must not be empty.',
                ], 422);
            }

            if (function_exists('mb_check_encoding') && !mb_check_encoding($yaml, 'UTF-8')) {
                return response()->json([
                    'message' => 'Knob YAML must be UTF-8 encoded.',
                ], 422);
            }

            if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
                try {
                    \Symfony\Component\Yaml\Yaml::parse($yaml);
                } catch (\Throwable $e) {
                    return response()->json([
                        'message' => 'Knob YAML is invalid.',
                        'detail' => $e->getMessage(),
                    ], 422);
                }
            }

            $url = "https://api-util.iquesters.com/v1/knobs/{$integrationUid}/" . urlencode($knobType);
            $method = strtolower($request->method()) === 'put' ? 'put' : 'post';

            $response = Http::withBody($yaml, 'text/plain')
                ->acceptJson()
                ->send(strtoupper($method), $url);

            if (!$response->successful()) {
                $payload = $response->json();

                Log::warning('Knob save API request failed', [
                    'integration_uid' => $integrationUid,
                    'knob_type' => $knobType,
                    'method' => strtoupper($method),
                    'status' => $response->status(),
                    'url' => $url,
                    'response' => $payload,
                ]);

                return response()->json(
                    is_array($payload) ? $payload : ['message' => 'Unable to save knob YAML.'],
                    $response->status()
                );
            }

            return response()->json($response->json());
        } catch (\Throwable $th) {
            Log::error('Knob save proxy error', [
                'integration_uid' => $integrationUid,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to save knob YAML.',
            ], 500);
        }
    }

    protected function saveIntegrationMeta(
        int $integrationId,
        string $key,
        $value,
        int $userId
    ): void {
        IntegrationMeta::updateOrCreate(
            [
                'ref_parent' => $integrationId,
                'meta_key'   => $key,
            ],
            [
                'meta_value' => $value,
                'status'     => Constants::ACTIVE,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }
}
