<?php

namespace Iquesters\Integration\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Iquesters\Integration\Models\Integration;
use Illuminate\Support\Facades\Log;
use Iquesters\Integration\Constants\Constants;
use Iquesters\Integration\Exceptions\ChatbotUtilFacebookException;
use Iquesters\Integration\Jobs\SyncVectorJob;
use Iquesters\Integration\Models\IntegrationMeta;
use Iquesters\Integration\Services\ChatbotUtilFacebookClient;

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
            $humanHandoverEnabled = (string) $integration->getMeta(Constants::HUMAN_HANDOVER_ENABLED, 'false');
            $allowInternalTesting = (string) $integration->getMeta(Constants::ALLOW_INTERNAL_TESTING, 'false');

            Log::info('Loading Integration Configuration', [
                'integration_uid' => $integrationUid,
                'has_website_url' => !empty($websiteUrl),
                'has_consumer_key' => !empty($consumerKey),
                'has_consumer_secret' => !empty($consumerSecret),
                'is_active' => $isActive,
                'human_handover_enabled' => $humanHandoverEnabled,
                'allow_internal_testing' => $allowInternalTesting,
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
                            'humanHandoverEnabled',
                            'allowInternalTesting'
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
            $humanHandoverEnabled = filter_var(
                $request->input('human_handover_enabled', 'false'),
                FILTER_VALIDATE_BOOLEAN
            ) ? 'true' : 'false';
            $allowInternalTesting = filter_var(
                $request->input('allow_internal_testing', 'false'),
                FILTER_VALIDATE_BOOLEAN
            ) ? 'true' : 'false';

            $this->saveIntegrationMeta(
                $integration->id,
                Constants::HUMAN_HANDOVER_ENABLED,
                $humanHandoverEnabled,
                $userId
            );

            $this->saveIntegrationMeta(
                $integration->id,
                Constants::ALLOW_INTERNAL_TESTING,
                $allowInternalTesting,
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

    public function startFacebookConnect(Request $request, ChatbotUtilFacebookClient $facebookClient)
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

            $responsePayload = $facebookClient->startFacebookConnect(
                $integration->uid,
                $validated['display_name'] ?? 'Facebook'
            );
            $utilStatus = $responsePayload['_util_http_status'] ?? null;
            unset($responsePayload['_util_http_status']);

            Log::info('facebook_connect_start_success', [
                'integration_uid' => $integration->uid,
                'integration_pk' => $integration->id,
                'util_http_status' => $utilStatus,
                'request_id' => $responsePayload['request_id'] ?? null,
            ]);

            return response()->json($responsePayload);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (ChatbotUtilFacebookException $e) {
            Log::warning('facebook_connect_start_failed', [
                'integration_id' => $request->input('integration_id'),
                'util_http_status' => $e->status(),
                'safe_error_code' => $e->errorCode(),
                'request_id' => $e->requestId(),
            ]);

            return response()->json([
                'message' => $this->facebookProxyErrorMessage($e, 'Failed to start Facebook onboarding.'),
                'error_code' => $e->errorCode(),
                'request_id' => $e->requestId(),
            ], $e->status());
        } catch (\Throwable $e) {
            Log::error('Facebook connect start proxy error', [
                'integration_id' => $request->input('integration_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $this->facebookProxyErrorMessage($e, 'Failed to start Facebook onboarding.'),
            ], $this->facebookProxyStatusCode($e));
        }
    }

    public function facebookPages(Request $request, ChatbotUtilFacebookClient $facebookClient)
    {
        try {
            $validated = $request->validate([
                'state' => 'required|string',
            ]);

            Log::info('facebook_pages_fetch_started', [
                'user_id' => auth()->id(),
                'state_ref' => $this->stateRef($validated['state']),
            ]);

            $responsePayload = $facebookClient->getFacebookPages($validated['state']);
            $utilStatus = $responsePayload['_util_http_status'] ?? null;
            unset($responsePayload['_util_http_status']);

            Log::info('facebook_pages_fetch_success', [
                'user_id' => auth()->id(),
                'state_ref' => $this->stateRef($validated['state']),
                'util_http_status' => $utilStatus,
                'page_count' => count($this->normalizeFacebookPages($responsePayload)),
                'request_id' => $responsePayload['request_id'] ?? null,
            ]);

            return response()->json($responsePayload);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (ChatbotUtilFacebookException $e) {
            $state = (string) $request->query('state', '');

            Log::warning('facebook_pages_fetch_failed', [
                'user_id' => auth()->id(),
                'state_ref' => $this->stateRef($state),
                'util_http_status' => $e->status(),
                'safe_error_code' => $e->errorCode(),
                'request_id' => $e->requestId(),
            ]);

            return response()->json([
                'message' => $this->facebookPagesUserMessage($e),
                'error_code' => $e->errorCode(),
                'request_id' => $e->requestId(),
            ], $e->status());
        } catch (\Throwable $e) {
            Log::error('Facebook pages proxy error', [
                'state_ref' => $this->stateRef((string) $request->query('state', '')),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Facebook setup is temporarily unavailable. Please try again.',
            ], $this->facebookProxyStatusCode($e));
        }
    }

    public function saveFacebookIntegration(Request $request, ChatbotUtilFacebookClient $facebookClient)
    {
        try {
            $validated = $request->validate([
                'state' => 'required|string',
                'page_id' => 'required|string',
                'integration_id' => 'nullable|string',
            ]);

            $integration = null;
            if (!empty($validated['integration_id'])) {
                $integration = Integration::where('uid', $validated['integration_id'])
                    ->whereHas('supportedIntegration', function ($query) {
                        $query->where('name', Constants::FACEBOOK_PAGE);
                    })
                    ->first();
            }

            if ($integration && $integration->getMeta('facebook_connection_status') === 'connected') {
                Log::info('facebook_integration_save_skipped_already_connected', [
                    'user_id' => auth()->id(),
                    'integration_uid' => $integration->uid,
                    'state_ref' => $this->stateRef($validated['state']),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Facebook connection already exists.',
                    'page_id' => $integration->getMeta('facebook_page_id'),
                ]);
            }

            Log::info('facebook_integration_save_started', [
                'user_id' => auth()->id(),
                'state_ref' => $this->stateRef($validated['state']),
                'selected_page_id' => $validated['page_id'],
            ]);

            Log::info('facebook_page_selected', [
                'user_id' => auth()->id(),
                'state_ref' => $this->stateRef($validated['state']),
                'selected_page_id' => $validated['page_id'],
            ]);

            $responsePayload = $facebookClient->saveFacebookIntegration(
                $validated['state'],
                $validated['page_id']
            );
            $utilStatus = $responsePayload['_util_http_status'] ?? null;
            unset($responsePayload['_util_http_status']);

            Log::info('facebook_integration_save_success', [
                'user_id' => auth()->id(),
                'state_ref' => $this->stateRef($validated['state']),
                'selected_page_id' => $validated['page_id'],
                'util_http_status' => $utilStatus,
                'request_id' => $responsePayload['request_id'] ?? null,
            ]);

            if ($integration && !empty($responsePayload['page_id'])) {
                $this->syncFacebookConnectionMeta(
                    $integration,
                    $responsePayload,
                    auth()->id() ?? 0
                );
            }

            return response()->json(
                is_array($responsePayload)
                    ? array_merge($responsePayload, [
                        'success' => true,
                    ])
                    : [
                        'success' => true,
                    ]
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (ChatbotUtilFacebookException $e) {
            $state = (string) $request->input('state', '');

            Log::warning('facebook_integration_save_failed', [
                'user_id' => auth()->id(),
                'state_ref' => $this->stateRef($state),
                'selected_page_id' => $request->input('page_id'),
                'util_http_status' => $e->status(),
                'safe_error_code' => $e->errorCode(),
                'request_id' => $e->requestId(),
            ]);

            return response()->json([
                'message' => $this->facebookSaveUserMessage($e),
                'error_code' => $e->errorCode(),
                'request_id' => $e->requestId(),
            ], $e->status());
        } catch (\Throwable $e) {
            Log::error('Facebook integration save proxy error', [
                'state_ref' => $this->stateRef((string) $request->input('state', '')),
                'page_id' => $request->input('page_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Facebook setup is temporarily unavailable. Please try again.',
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

    protected function syncFacebookConnectionMeta(
        Integration $integration,
        array $responsePayload,
        int $userId
    ): void {
        try {
            $pageId = (string) ($responsePayload['page_id'] ?? '');
            $pageName = $responsePayload['page_name'] ?? null;
            $facebookIntegrationId = $responsePayload['facebook_integration_id'] ?? null;

            if ($pageId === '') {
                return;
            }

            $this->saveIntegrationMeta($integration->id, 'facebook_connection_status', 'connected', $userId);
            $this->saveIntegrationMeta($integration->id, 'facebook_page_id', $pageId, $userId);
            $this->saveIntegrationMeta($integration->id, 'facebook_page_name', $pageName, $userId);
            if (!empty($facebookIntegrationId)) {
                $this->saveIntegrationMeta($integration->id, 'facebook_integration_id', $facebookIntegrationId, $userId);
            }

            $integration->update([
                'status' => Constants::ACTIVE,
                'updated_by' => $userId,
            ]);

            Log::info('facebook_integration_meta_synced', [
                'user_id' => auth()->id(),
                'integration_uid' => $integration->uid,
                'page_id' => $pageId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('facebook_integration_meta_sync_failed', [
                'user_id' => auth()->id(),
                'integration_uid' => $integration->uid,
                'error' => $e->getMessage(),
            ]);
        }
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

    protected function facebookPagesUserMessage(ChatbotUtilFacebookException $e): string
    {
        if ($e->status() === 404) {
            return 'This Facebook connection is invalid or expired. Please start again.';
        }

        if ($e->status() === 409) {
            return 'Facebook authorization is still finishing. Please try again in a few seconds.';
        }

        if ($this->isTokenSessionError($e)) {
            return 'We could not complete Facebook authorization. Please reconnect.';
        }

        if ($e->status() >= 500) {
            return 'Facebook setup is temporarily unavailable. Please try again.';
        }

        return 'Unable to load Facebook Pages. Please try again.';
    }

    protected function facebookSaveUserMessage(ChatbotUtilFacebookException $e): string
    {
        if ($this->isAlreadyCompletedError($e)) {
            return 'This Facebook connection has already been completed.';
        }

        if (in_array($e->status(), [404, 410], true)) {
            return 'This connection session expired. Please start again.';
        }

        if ($e->status() === 422) {
            return 'Selected page is no longer available. Please choose another page or reconnect.';
        }

        if ($e->status() >= 500) {
            return 'Facebook setup is temporarily unavailable. Please try again.';
        }

        return 'Unable to save Facebook integration. Please try again.';
    }

    protected function isTokenSessionError(ChatbotUtilFacebookException $e): bool
    {
        $errorCode = strtolower((string) $e->errorCode());

        return str_contains($errorCode, 'token')
            || str_contains($errorCode, 'session');
    }

    protected function isAlreadyCompletedError(ChatbotUtilFacebookException $e): bool
    {
        $errorCode = strtolower((string) $e->errorCode());
        $message = strtolower($e->getMessage());

        return str_contains($errorCode, 'already')
            || str_contains($message, 'already completed');
    }

    protected function normalizeFacebookPages(array $payload): array
    {
        $pages = $payload['pages']
            ?? $payload['data']
            ?? data_get($payload, 'result.pages')
            ?? [];

        return is_array($pages) ? $pages : [];
    }

    protected function stateRef(?string $state): ?string
    {
        $state = trim((string) $state);

        if ($state === '') {
            return null;
        }

        return substr(hash('sha256', $state), 0, 16);
    }
}
