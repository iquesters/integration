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
                case Constants::FACEBOOK_PAGE:
                    return view(
                        'integration::integrations.facebook.config',
                        compact('integration')
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

    public function startFacebookConnect(Request $request)
    {
        try {
            $validated = $request->validate([
                'integration_id' => 'required|string',
                'display_name' => 'nullable|string',
                'redirect_target' => 'nullable|url',
            ]);

            $integration = Integration::where('uid', $validated['integration_id'])
                ->whereHas('supportedIntegration', function ($query) {
                    $query->where('name', Constants::FACEBOOK_PAGE);
                })
                ->firstOrFail();

            $payload = [
                'integration_id' => $integration->uid,
                'display_name' => $validated['display_name'] ?? $integration->name,
                'redirect_target' => $validated['redirect_target'] ?? null,
            ];

            $url = $this->facebookApiUrl($integration, 'facebook_api_url');
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(20)
                ->post($url, $payload);
            $responsePayload = $response->json();

            Log::debug('Facebook connect start API attempt', [
                'integration_uid' => $integration->uid,
                'integration_pk' => $integration->id,
                'attempt_integration_id' => $integration->uid,
                'status' => $response->status(),
                'response' => $responsePayload,
            ]);

            Log::debug('Facebook connect start API response', [
                'integration_uid' => $integration->uid,
                'integration_pk' => $integration->id,
                'status' => $response->status(),
                'response' => $responsePayload,
            ]);

            if (!$response->successful()) {
                Log::warning('Facebook connect start API request failed', [
                    'integration_uid' => $integration->uid,
                    'integration_pk' => $integration->id,
                    'status' => $response->status(),
                    'url' => $url,
                    'response' => $responsePayload,
                ]);

                return response()->json(
                    is_array($responsePayload) ? $responsePayload : ['message' => 'Unable to start Facebook onboarding.'],
                    $response->status()
                );
            }

            return response()->json($this->withFacebookPopupDisplay($responsePayload));
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Facebook connect start proxy error', [
                'integration_id' => $request->input('integration_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => $this->facebookProxyErrorMessage($e, 'Failed to start Facebook onboarding.'),
            ], $this->facebookProxyStatusCode($e));
        }
    }

    protected function withFacebookPopupDisplay($payload)
    {
        if (!is_array($payload)) {
            return $payload;
        }

        foreach (['authorization_url', 'auth_url', 'redirect_url', 'url'] as $key) {
            if (!empty($payload[$key]) && is_string($payload[$key])) {
                $payload[$key] = $this->appendQueryParameter($payload[$key], 'display', 'popup');
                break;
            }
        }

        return $payload;
    }

    protected function appendQueryParameter(string $url, string $key, string $value): string
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $query[$key] = $value;

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $path = $parts['path'] ?? '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return "{$parts['scheme']}://{$auth}{$parts['host']}{$port}{$path}?" . http_build_query($query) . $fragment;
    }

    public function facebookPages(Request $request)
    {
        try {
            $validated = $request->validate([
                'state' => 'required|string',
                'integration_id' => 'required|string',
            ]);

            $integration = Integration::where('uid', $validated['integration_id'])
                ->whereHas('supportedIntegration', function ($query) {
                    $query->where('name', Constants::FACEBOOK_PAGE);
                })
                ->firstOrFail();

            $url = $this->facebookApiUrl($integration, 'facebook_pages_url');

            $response = Http::acceptJson()
                ->timeout(20)
                ->get($url, [
                    'state' => $validated['state'],
                ]);

            $responsePayload = $response->json();

            if (!$response->successful()) {
                Log::warning('Facebook pages API request failed', [
                    'status' => $response->status(),
                    'url' => $url,
                    'response' => $responsePayload,
                ]);

                return response()->json(
                    is_array($responsePayload) ? $responsePayload : ['message' => 'Unable to load Facebook pages.'],
                    $response->status()
                );
            }

            return response()->json($responsePayload);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Facebook pages proxy error', [
                'state' => $request->query('state'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => $this->facebookProxyErrorMessage($e, 'Failed to load Facebook pages.'),
            ], $this->facebookProxyStatusCode($e));
        }
    }

    public function saveFacebookIntegration(Request $request)
    {
        try {
            $validated = $request->validate([
                'state' => 'required_without:page_access_token|nullable|string',
                'page_id' => 'required|string',
                'page_name' => 'nullable|string',
                'integration_id' => 'required|string',
                'page_access_token' => 'required_without:state|nullable|string',
                'user_access_token' => 'nullable|string',
            ]);

            $integration = Integration::where('uid', $validated['integration_id'])
                ->whereHas('supportedIntegration', function ($query) {
                    $query->where('name', Constants::FACEBOOK_PAGE);
                })
                ->firstOrFail();

            if (!empty($validated['page_access_token'])) {
                $userId = auth()->id() ?? 0;
                $pageName = $validated['page_name'] ?? null;

                $this->saveIntegrationMeta($integration->id, 'facebook_page_id', $validated['page_id'], $userId);
                $this->saveIntegrationMeta($integration->id, 'facebook_page_name', $pageName, $userId);
                $this->saveIntegrationMeta($integration->id, 'facebook_page_access_token', $validated['page_access_token'], $userId);

                if (!empty($validated['user_access_token'])) {
                    $this->saveIntegrationMeta($integration->id, 'facebook_user_access_token', $validated['user_access_token'], $userId);
                }

                $this->saveIntegrationMeta($integration->id, 'facebook_connection_status', 'connected', $userId);

                $integration->update([
                    'status' => Constants::ACTIVE,
                    'updated_by' => $userId,
                ]);

                return response()->json([
                    'success' => true,
                    'page_id' => $validated['page_id'],
                    'page_name' => $pageName,
                    'redirect' => route('integration.configure', ['integrationUid' => $integration->uid]),
                ]);
            }

            $payload = [
                'state' => $validated['state'],
                'page_id' => $validated['page_id'],
            ];

            $url = $this->facebookApiUrl($integration, 'facebook_integration_save_url');

            $response = Http::acceptJson()
                ->asJson()
                ->timeout(20)
                ->post($url, $payload);

            $responsePayload = $response->json();

            if (!$response->successful()) {
                Log::warning('Facebook integration save API request failed', [
                    'integration_uid' => $integration->uid,
                    'integration_pk' => $integration->id,
                    'status' => $response->status(),
                    'url' => $url,
                    'response' => $responsePayload,
                ]);

                return response()->json(
                    is_array($responsePayload) ? $responsePayload : ['message' => 'Unable to save Facebook integration.'],
                    $response->status()
                );
            }

            $userId = auth()->id() ?? 0;
            $pageName = data_get($responsePayload, 'page_name')
                ?? data_get($responsePayload, 'page.name')
                ?? ($validated['page_name'] ?? null);

            $this->saveIntegrationMeta($integration->id, 'facebook_page_id', $validated['page_id'], $userId);
            $this->saveIntegrationMeta($integration->id, 'facebook_page_name', $pageName, $userId);
            $this->saveIntegrationMeta($integration->id, 'facebook_connection_status', 'connected', $userId);

            $integration->update([
                'status' => Constants::ACTIVE,
                'updated_by' => $userId,
            ]);

            return response()->json(
                is_array($responsePayload)
                    ? array_merge($responsePayload, [
                        'success' => true,
                        'redirect' => route('integration.configure', ['integrationUid' => $integration->uid]),
                    ])
                    : [
                        'success' => true,
                        'redirect' => route('integration.configure', ['integrationUid' => $integration->uid]),
                    ]
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Facebook integration save proxy error', [
                'integration_id' => $request->input('integration_id'),
                'page_id' => $request->input('page_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => $this->facebookProxyErrorMessage($e, 'Failed to save Facebook integration.'),
            ], $this->facebookProxyStatusCode($e));
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

    protected function facebookApiUrl(Integration $integration, string $metaKey): string
    {
        $url = trim((string) $integration->supportedIntegration?->getMeta($metaKey));

        if ($url === '') {
            throw new \RuntimeException("{$metaKey} is not configured.");
        }

        return $url;
    }

    protected function facebookProxyErrorMessage(\Throwable $e, string $fallback): string
    {
        return str_ends_with($e->getMessage(), 'is not configured.')
            ? $e->getMessage()
            : $fallback;
    }

    protected function facebookProxyStatusCode(\Throwable $e): int
    {
        return str_ends_with($e->getMessage(), 'is not configured.')
            ? 503
            : 500;
    }
}
