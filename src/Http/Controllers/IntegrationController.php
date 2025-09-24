<?php

namespace Iquesters\Integration\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Iquesters\Integration\Models\Integration;
use Iquesters\Integration\Models\OrganisationIntegration;
use Iquesters\Integration\Models\OrganisationIntegrationMeta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Iquesters\Integration\Models\IntegrationMeta;

class IntegrationController extends Controller
{
    /**
     * Display all integrations with their meta data.
     */
    public function index($organisationUid)
    {
        $organisation = null;

        // Use real Organisation model if available
        if (class_exists(\Iquesters\Organisation\Models\Organisation::class)) {
            $organisation = \Iquesters\Organisation\Models\Organisation::where('uid', $organisationUid)->first();
        }

        // Fallback dummy organisation if not available
        if (!$organisation) {
            $organisation = new class {
                public $id = 1;
                public $uid;
                public $name = 'Default Organisation';

                // Check if this integration is active in the DB
                public function hasActiveIntegration($integrationId)
                {
                    return OrganisationIntegration::where('organisation_id', $this->id)
                        ->where('integration_masterdata_id', $integrationId)
                        ->where('status', 'active')
                        ->exists();
                }

                public function organisationIntegrations()
                {
                    $orgId = $this->id;

                    return new class($orgId) {
                        private $orgId;
                        public function __construct($id)
                        {
                            $this->orgId = $id;
                        }

                        public function where($col, $val)
                        {
                            $this->integrationId = $val;
                            return $this;
                        }

                        public function first()
                        {
                            return OrganisationIntegration::where('organisation_id', $this->orgId)
                                ->where('integration_masterdata_id', $this->integrationId)
                                ->first();
                        }

                        public function create(array $data)
                        {
                            $data['organisation_id'] = $this->orgId;
                            return OrganisationIntegration::create($data);
                        }
                    };
                }
            };
            $organisation->uid = $organisationUid;
        }

        $applicationNames = Integration::where('status', 'active')->get();

        return view('integration::integrations.index', compact('organisation', 'applicationNames'));
    }

    /**
     * Toggle integration for an organisation (activate / deactivate)
     */
    public function toggleIntegration(Request $request, $organisationUid, $integrationUid)
    {
        try {
            $organisation = null;

            if (class_exists(\Iquesters\Organisation\Models\Organisation::class)) {
                $organisation = \Iquesters\Organisation\Models\Organisation::where('uid', $organisationUid)->first();
            }

            if (!$organisation) {
                // Fallback dummy organisation
                $organisation = new class {
                    public $id = 1;
                    public $uid;
                    public $name = 'Default Organisation';

                    public function organisationIntegrations()
                    {
                        $orgId = $this->id;

                        return new class($orgId) {
                            private $orgId;
                            public function __construct($id)
                            {
                                $this->orgId = $id;
                            }

                            public function where($col, $val)
                            {
                                $this->integrationId = $val;
                                return $this;
                            }

                            public function first()
                            {
                                return OrganisationIntegration::where('organisation_id', $this->orgId)
                                    ->where('integration_masterdata_id', $this->integrationId)
                                    ->first();
                            }

                            public function create(array $data)
                            {
                                $data['organisation_id'] = $this->orgId;
                                return OrganisationIntegration::create($data);
                            }
                        };
                    }

                    public function hasActiveIntegration($integrationId)
                    {
                        return OrganisationIntegration::where('organisation_id', $this->id)
                            ->where('integration_masterdata_id', $integrationId)
                            ->where('status', 'active')
                            ->exists();
                    }
                };
                $organisation->uid = $organisationUid;
            }

            $integration = Integration::where('uid', $integrationUid)->firstOrFail();
            $action = $request->input('action');

            if (!in_array($action, ['activate', 'deactivate'])) {
                return redirect()->back()->with('error', 'Invalid action specified');
            }

            $status = $action === 'activate' ? 'active' : 'inactive';

            $organisationIntegration = $organisation->organisationIntegrations()
                ->where('integration_masterdata_id', $integration->id)
                ->first();

            if ($organisationIntegration) {
                if ($organisationIntegration->status === $status) {
                    $statusText = $status === 'active' ? 'already active' : 'already inactive';
                    return redirect()->back()->with('error', "Integration is {$statusText}");
                }

                $organisationIntegration->update([
                    'status' => $status,
                    'updated_by' => Auth::id(),
                ]);
            } else {
                $organisationIntegration = $organisation->organisationIntegrations()->create([
                    'integration_masterdata_id' => $integration->id,
                    'status' => $status,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            }

            $actionText = $status === 'active' ? 'activated' : 'deactivated';
            Log::info("Integration {$actionText}", [
                'organisation_id' => $organisation->id,
                'integration_id' => $integration->id,
                'user_id' => Auth::id()
            ]);

            return redirect()->back()->with('success', "Integration {$actionText} successfully");
        } catch (\Exception $e) {
            Log::error('Error toggling integration', [
                'error' => $e->getMessage(),
                'organisation_uid' => $organisationUid,
                'integration_uid' => $integrationUid,
                'action' => $request->input('action')
            ]);

            return redirect()->back()->with('error', 'An error occurred while processing your request');
        }
    }

    public function showZohoBooks($organisationUid, $integrationUid)
    {
        Log::info('display information related to zohobooks');

        // ✅ Try to resolve Organisation
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

        // ✅ Get Integration definition
        $zohoBooksIntegration = Integration::where('uid', $integrationUid)->firstOrFail();
        $shortName = $zohoBooksIntegration->small_name ?? '_';

        // ✅ Get org-specific integration record
        $integration = OrganisationIntegration::where('organisation_id', $organisation->id)
            ->where('integration_masterdata_id', $zohoBooksIntegration->id)
            ->first();

        // ----------------------------
        // Entity Config decode
        $existingEntityConfig = [];

        if ($integration) {
            $entityConfigMeta = $integration->metas()
                ->where('meta_key', 'entity_configuration')
                ->first();

            if ($entityConfigMeta && $entityConfigMeta->meta_value) {
                $existingEntityConfig = json_decode($entityConfigMeta->meta_value, true) ?? [];
                Log::info('Decoded Entity Config', $existingEntityConfig);
            }
        }

        // ✅ Default values for Zoho tokens/scopes
        $clientId = $clientSecret = $code = $accessToken = $refreshToken = $organisationId = null;
        $selectedScopes = [];
        $accessTokenCreatedAt = null;

        if ($integration) {
            $clientId        = optional($integration->metas()->where('meta_key', "{$shortName}_client_id")->first())->meta_value;
            $clientSecret    = optional($integration->metas()->where('meta_key', "{$shortName}_client_secret")->first())->meta_value;
            $scopesMeta      = optional($integration->metas()->where('meta_key', "{$shortName}_scope")->first())->meta_value;
            $code            = optional($integration->metas()->where('meta_key', "{$shortName}_code")->first())->meta_value;
            $accessTokenMeta = $integration->metas()->where('meta_key', "{$shortName}_access_token")->first();
            $refreshToken    = optional($integration->metas()->where('meta_key', "{$shortName}_refresh_token")->first())->meta_value;
            $organisationId  = optional($integration->metas()->where('meta_key', "{$shortName}_organisation_id")->first())->meta_value;

            $selectedScopes = $scopesMeta ? explode(',', $scopesMeta) : [];
            $accessToken    = $accessTokenMeta ? $accessTokenMeta->meta_value : null;
            $accessTokenCreatedAt = $accessTokenMeta ? $accessTokenMeta->updated_at : null;
        }

        // ✅ Figure out integration status
        $integrationStatus = $this->getZohoIntegrationStatus(
            $clientId,
            $clientSecret,
            $accessToken,
            $refreshToken,
            $code,
            $selectedScopes,
            $accessTokenCreatedAt,
            $organisationId
        );

        Log::info('integration status', ['integrationStatus' => $integrationStatus]);

        // ✅ Selected APIs
        $selectedApis = $integration ? $integration->getSelectedZohoBooksMetas() : collect();
        Log::info('selected apis', ['selectedApis' => $selectedApis]);

        return view('integration::integrations.zoho_books.zoho-books', [
            'organisation'        => $organisation,
            'integrationStatus'   => $integrationStatus,
            'application'         => $zohoBooksIntegration,
            'metas'               => $zohoBooksIntegration->metas()
                ->where('meta_key', 'like', 'api_%')
                ->where('status', 'active')
                ->get(),
            'selectedApis'        => $selectedApis,
            'integration'         => $integration,
            'clientId'            => $clientId,
            'clientSecret'        => $clientSecret,
            'code'                => $code,
            'organisationId'      => $organisationId,
            'selectedScopes'      => $selectedScopes,
            'existingEntityConfig' => $existingEntityConfig,
        ]);
    }

    /**
     * Get Zoho Books integration status with security in mind
     */
    private function getZohoIntegrationStatus($clientId, $clientSecret, $accessToken, $refreshToken, $code, $selectedScopes, $accessTokenCreatedAt, $organisationId)
    {
        // Zoho Books access token typically expires in 1 hour (3600 seconds)
        $tokenExpiryMinutes = 60; // 60 minutes = 1 hour

        $hasAccessToken = !empty($accessToken);
        $hasRefreshToken = !empty($refreshToken);

        // Check if access token is expired
        $isTokenExpired = false;
        $tokenExpiresAt = null;
        $tokenAge = null;

        if ($hasAccessToken && $accessTokenCreatedAt) {
            $tokenCreatedAt = \Carbon\Carbon::parse($accessTokenCreatedAt);
            $tokenExpiresAt = $tokenCreatedAt->addMinutes($tokenExpiryMinutes);
            $tokenAge = $tokenCreatedAt->diffInMinutes(now());
            $isTokenExpired = now()->isAfter($tokenExpiresAt);
        }

        // Determine overall connection status
        $connectionStatus = $this->determineConnectionStatus(
            $clientId,
            $clientSecret,
            $hasAccessToken,
            $hasRefreshToken,
            $isTokenExpired,
        );

        return [
            // Configuration status
            'has_client_id' => !empty($clientId),
            'has_client_secret' => !empty($clientSecret),
            'has_auth_code' => !empty($code),
            'scopes_configured' => count($selectedScopes) > 0,
            'selected_scopes_count' => count($selectedScopes),
            'scopes_list' => $selectedScopes,
            'organisation_id' => !empty($organisationId),

            // Token status
            'has_access_token' => $hasAccessToken,
            'has_refresh_token' => $hasRefreshToken,
            'is_access_token_expired' => $isTokenExpired,
            'token_age_minutes' => $tokenAge,
            'token_expires_at' => $tokenExpiresAt ? $tokenExpiresAt->format('Y-m-d H:i:s') : null,
            'token_expires_at_human' => $tokenExpiresAt ? $tokenExpiresAt->diffForHumans() : null,

            // Overall status
            'is_fully_configured' => !empty($clientId) && !empty($clientSecret),
            'is_authenticated' => $hasAccessToken && !$isTokenExpired,
            'connection_status' => $connectionStatus,
            'can_refresh_token' => $hasRefreshToken && $isTokenExpired,

            // Action needed
            'needs_authentication' => !$hasAccessToken || $isTokenExpired,
            'needs_configuration' => empty($clientId) || empty($clientSecret),
        ];
    }

    /**
     * Determine the overall connection status
     */
    private function determineConnectionStatus($clientId, $clientSecret, $hasAccessToken, $hasRefreshToken, $isTokenExpired)
    {
        if (empty($clientId) || empty($clientSecret)) {
            return 'not_configured';
        }

        if (!$hasAccessToken) {
            return 'not_authenticated';
        }

        if ($isTokenExpired && !$hasRefreshToken) {
            return 'expired_no_refresh';
        }

        if ($isTokenExpired && $hasRefreshToken) {
            return 'expired_can_refresh';
        }

        return 'connected';
    }
    
    public function saveZohoBooksIntegration(Request $request, $organisationUid, $integrationUid)
    {
        try {
            Log::info('Saving Zoho Books integration settings', [
                'organisation_uid' => $organisationUid,
                'request_data'     => $request->all()
            ]);

            // ✅ Resolve Organisation or fallback
            $organisation = null;
            if (class_exists(\Iquesters\Organisation\Models\Organisation::class)) {
                $organisation = \Iquesters\Organisation\Models\Organisation::where('uid', $organisationUid)->firstOrFail();
            }

            if (!$organisation) {
                // Fallback dummy organisation (id=1, like earlier methods)
                $organisation = new class {
                    public $id = 1;
                    public $uid;
                    public $name = 'Default Organisation';
                };
                $organisation->uid = $organisationUid;
            }

            $userId = Auth::id();

            // ✅ Get the Zoho Books integration definition
            $zohoBooksIntegration = Integration::where('uid', $integrationUid)->firstOrFail();
            $shortName = $zohoBooksIntegration->small_name ?? '_';

            // ✅ Get or create the parent integration record
            $parentIntegration = OrganisationIntegration::firstOrCreate(
                [
                    'organisation_id'         => $organisation->id,
                    'integration_masterdata_id' => $zohoBooksIntegration->id
                ],
                [
                    'created_by' => $userId,
                    'updated_by' => $userId
                ]
            );

            // ✅ Handle client credentials form
            if ($request->has('client_id') && $request->has('client_secret')) {
                $request->validate([
                    'client_id'     => 'required|string|max:255',
                    'client_secret' => 'required|string|max:500'
                ]);

                OrganisationIntegrationMeta::updateOrCreate(
                    [
                        'ref_parent' => $parentIntegration->id,
                        'meta_key'   => "{$shortName}_client_id"
                    ],
                    [
                        'meta_value' => $request->client_id,
                        'status'     => 'active',
                        'created_by' => $userId,
                        'updated_by' => $userId
                    ]
                );

                OrganisationIntegrationMeta::updateOrCreate(
                    [
                        'ref_parent' => $parentIntegration->id,
                        'meta_key'   => "{$shortName}_client_secret"
                    ],
                    [
                        'meta_value' => $request->client_secret,
                        'status'     => 'active',
                        'created_by' => $userId,
                        'updated_by' => $userId
                    ]
                );

                return redirect()->back()->with('success', 'Client credentials saved successfully!');
            }

            // ✅ Handle scopes form
            if ($request->has('scopes')) {
                $request->validate([
                    'scopes'   => 'required|array|min:1',
                    'scopes.*' => 'string'
                ]);

                OrganisationIntegrationMeta::updateOrCreate(
                    [
                        'ref_parent' => $parentIntegration->id,
                        'meta_key'   => "{$shortName}_scope"
                    ],
                    [
                        'meta_value' => implode(',', $request->scopes),
                        'status'     => 'active',
                        'created_by' => $userId,
                        'updated_by' => $userId
                    ]
                );

                return redirect()->back()->with('success', 'Scopes saved successfully!');
            }

            // ✅ Handle code from copied URL
            if ($request->has('copid_url_code')) {
                $request->validate([
                    'copid_url_code' => 'required|string|max:3000',
                ]);

                $copidUrl = $request->copid_url_code;
                $parsedUrl = parse_url($copidUrl);
                parse_str($parsedUrl['query'] ?? '', $queryParams);
                $code = $queryParams['code'] ?? null;

                if (!$code) {
                    return redirect()->back()->with('error', 'Code parameter not found in the URL.');
                }

                OrganisationIntegrationMeta::updateOrCreate(
                    [
                        'ref_parent' => $parentIntegration->id,
                        'meta_key'   => "{$shortName}_code"
                    ],
                    [
                        'meta_value' => $code,
                        'status'     => 'active',
                        'created_by' => $userId,
                        'updated_by' => $userId
                    ]
                );

                return redirect()->back()->with('success', 'Code extracted and saved successfully!');
            }

            // ✅ Handle organisation_id
            if ($request->has('organisation_id')) {
                $request->validate([
                    'organisation_id' => 'required|string|max:255',
                ]);

                OrganisationIntegrationMeta::updateOrCreate(
                    [
                        'ref_parent' => $parentIntegration->id,
                        'meta_key'   => "{$shortName}_organisation_id"
                    ],
                    [
                        'meta_value' => $request->organisation_id,
                        'status'     => 'active',
                        'created_by' => $userId,
                        'updated_by' => $userId
                    ]
                );

                return redirect()->back()->with('success', 'Organisation ID saved successfully!');
            }

            throw new \Exception('Invalid form submission');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error saving Zoho Books integration: ' . $e->getMessage(), [
                'organisation_uid' => $organisationUid,
                'request_data'     => $request->all(),
                'trace'            => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Unable to save integration settings. Please try again.');
        }
    }

    public function saveApiName(Request $request, $organisationUid, $integrationUid)
    {
        try {
            Log::info('Saving API selection and scopes', [
                'organisation_uid' => $organisationUid,
                'integration_uid' => $integrationUid,
                'request_data' => $request->all()
            ]);

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

            $integration = OrganisationIntegration::where('organisation_id', $organisation->id)
                ->where('integration_masterdata_id', Integration::where('uid', $integrationUid)->firstOrFail()->id)
                ->firstOrFail();

            $selectedMetas = $request->input('selected_metas', []);
            $selectedMetas = array_map('intval', $selectedMetas);

            // Validate that at least one API is selected
            if (empty($selectedMetas)) {
                throw new \Exception('Please select at least one API.');
            }

            $thirdPartyIntegration = Integration::where('uid', $integrationUid)
                ->firstOrFail();
            // Get the short name for meta key prefix
            $shortName = $thirdPartyIntegration->small_name ?? '_';
            $userId = Auth::id();

            // Extract and collect all OAuth scopes from selected metas
            $scopes = [];
            foreach ($selectedMetas as $metaId) {
                $meta = IntegrationMeta::find($metaId);

                if (!$meta) {
                    Log::warning('Integration meta not found', ['meta_id' => $metaId]);
                    continue;
                }

                if (empty($meta->meta_value)) {
                    Log::warning('Integration meta value is empty', ['meta_id' => $metaId]);
                    continue;
                }

                $metaData = json_decode($meta->meta_value, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('Failed to decode JSON meta value', [
                        'meta_id' => $metaId,
                        'json_error' => json_last_error_msg()
                    ]);
                    continue;
                }

                if (isset($metaData['oauth_scope'])) {
                    $scopes[] = $metaData['oauth_scope'];
                } else {
                    Log::warning('oauth_scope not found in meta data', ['meta_id' => $metaId]);
                }
            }

            // Validate that at least one scope was found
            if (empty($scopes)) {
                throw new \Exception('No OAuth scopes found in the selected APIs. Please ensure the APIs have valid scope configurations.');
            }

            // Save selected meta IDs as JSON
            $integration->metas()->updateOrCreate(
                ['meta_key' => "{$shortName}_api_id"],
                [
                    'meta_value' => json_encode($selectedMetas),
                    'status'     => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );

            // Save the collected scopes as comma-separated string
            $integration->metas()->updateOrCreate(
                ['meta_key' => "{$shortName}_scope"],
                [
                    'meta_value' => implode(',', array_unique($scopes)),
                    'status'     => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );

            Log::info('API selection and scopes saved successfully', [
                'organisation_id' => $organisation->id,
                'integration_id' => $integration->id,
                'selected_metas_count' => count($selectedMetas),
                'scopes_count' => count($scopes)
            ]);

            return redirect()->back()->with('success', 'API selection and scopes saved successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Resource not found in saveApiName: ' . $e->getMessage(), [
                'organisation_uid' => $organisationUid,
                'integration_uid' => $integrationUid
            ]);

            return redirect()->back()->with('error', 'Organisation or integration not found.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation error in saveApiName', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);

            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error in saveApiName: ' . $e->getMessage(), [
                'organisation_uid' => $organisationUid,
                'integration_uid' => $integrationUid,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Unable to save API selection. ' . $e->getMessage());
        }
    }

    public function getTokens(Request $request, $organisationUid, $integrationUid)
    {
        try {
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
            $userId = Auth::id();

            // Get the Zoho Books integration record
            $zohoBooksIntegration = Integration::where('uid', $integrationUid)
                ->firstOrFail();

            $shortName = $zohoBooksIntegration->small_name ?? '_';

            $integration = OrganisationIntegration::where('organisation_id', $organisation->id)
                ->where('integration_masterdata_id', $zohoBooksIntegration->id)
                ->firstOrFail();

            // Get required meta values
            $clientId = $integration->metas()->where('meta_key', "{$shortName}_client_id")->firstOrFail();
            $clientSecret = $integration->metas()->where('meta_key', "{$shortName}_client_secret")->firstOrFail();
            $codeMeta = $integration->metas()->where('meta_key', "{$shortName}_code")->firstOrFail();

            $code = $codeMeta->meta_value;

            // Debug log the parameters (masking sensitive values)
            Log::debug('Attempting Zoho token exchange', [
                'client_id' => substr($clientId->meta_value, 0, 5) . '...',
                'code' => substr($code, 0, 5) . '...',
                'redirect_uri' => 'https://trackshoot.com/login'
            ]);

            // Prepare the request to Zoho
            $client = new Client();
            $response = $client->post('https://accounts.zoho.in/oauth/v2/token', [
                'form_params' => [
                    'code' => $code,
                    'client_id' => $clientId->meta_value,
                    'client_secret' => $clientSecret->meta_value,
                    'redirect_uri' => 'https://trackshoot.com/login',
                    'grant_type' => 'authorization_code'
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'http_errors' => false
            ]);

            $responseData = json_decode($response->getBody(), true);

            Log::debug('Zoho token response', [
                'status_code' => $response->getStatusCode(),
                'response' => $responseData
            ]);

            if ($response->getStatusCode() !== 200) {
                $error = $responseData['error'] ?? 'Unknown error occurred';
                throw new \Exception("Zoho API Error ({$response->getStatusCode()}): {$error}");
            }

            if (isset($responseData['error'])) {
                // Handle specific error cases
                if ($responseData['error'] === 'invalid_code') {
                    // Clear the stored code since it's invalid
                    $codeMeta->delete();

                    throw new \Exception('The authorization code has expired or is invalid. Please re-authenticate with Zoho to get a new code.');
                }
                throw new \Exception($responseData['error']);
            }

            if (!isset($responseData['access_token']) || !isset($responseData['refresh_token'])) {
                throw new \Exception('Invalid response from Zoho - missing tokens');
            }

            // Save the tokens
            OrganisationIntegrationMeta::updateOrCreate(
                ['ref_parent' => $integration->id, 'meta_key' => "{$shortName}_access_token"],
                [
                    'meta_value' => $responseData['access_token'],
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId
                ]
            );

            OrganisationIntegrationMeta::updateOrCreate(
                ['ref_parent' => $integration->id, 'meta_key' => "{$shortName}_refresh_token"],
                [
                    'meta_value' => $responseData['refresh_token'],
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId
                ]
            );

            return redirect()->back()->with('success', 'Tokens generated and saved successfully!');
        } catch (\Exception $e) {
            Log::error('Error generating Zoho tokens: ' . $e->getMessage(), [
                'organisation_uid' => $organisationUid,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', $e->getMessage());
        }
    }
    
    public function regenerateAccessToken(Request $request, $organisationUid, $integrationUid)
    {
        try {
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
            $userId = Auth::id();

            // Get the Zoho Books integration record
            $zohoBooksIntegration = Integration::where('uid', $integrationUid)
                ->firstOrFail();

            $shortName = $zohoBooksIntegration->small_name ?? '_';

            $integration = OrganisationIntegration::where('organisation_id', $organisation->id)
                ->where('integration_masterdata_id', $zohoBooksIntegration->id)
                ->firstOrFail();

            // Get required meta values
            $clientId = $integration->metas()->where('meta_key', "{$shortName}_client_id")->firstOrFail();
            $clientSecret = $integration->metas()->where('meta_key', "{$shortName}_client_secret")->firstOrFail();
            $refreshToken = $integration->metas()->where('meta_key', "{$shortName}_refresh_token")->firstOrFail();
            $redirectUri = 'https://trackshoot.com/login';

            // Prepare request parameters
            $params = [
                'refresh_token' => $refreshToken->meta_value,
                'client_id' => $clientId->meta_value,
                'client_secret' => $clientSecret->meta_value,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'refresh_token'
            ];

            Log::debug('Attempting Zoho refresh token exchange', [
                'client_id' => substr($clientId->meta_value, 0, 5) . '...',
                'refresh_token' => substr($refreshToken->meta_value, 0, 5) . '...',
                'redirect_uri' => $redirectUri
            ]);

            // Prepare the request to Zoho
            $client = new Client();
            $response = $client->post('https://accounts.zoho.in/oauth/v2/token', [
                'form_params' => $params,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'http_errors' => false
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

            // Update the access token
            OrganisationIntegrationMeta::updateOrCreate(
                ['ref_parent' => $integration->id, 'meta_key' => "{$shortName}_access_token"],
                [
                    'meta_value' => $responseData['access_token'],
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId
                ]
            );

            return redirect()->back()->with('success', 'Access token regenerated successfully! The new token will expire in 1 hour.');
        } catch (\Exception $e) {
            Log::error('Error regenerating Zoho access token: ' . $e->getMessage(), [
                'organisation_uid' => $organisationUid,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', $e->getMessage());
        }
    }
    
    /**
     * Save entity configuration from the modal
     */
    public function saveEntityConfiguration(Request $request, $organisationUid, $integrationUid)
    {
        try {
            $request->validate([
                'entity_names' => 'required|string|max:500',
                'default_entity' => 'required|string|max:255'
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

            $integration = Integration::where('uid', $integrationUid)->firstOrFail();
            $userId = Auth::id();

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

            // Save entity configuration
            $entityConfig = [
                'entity_names' => $request->entity_names,
                'default_entity' => $request->default_entity
            ];

            OrganisationIntegrationMeta::updateOrCreate(
                [
                    'ref_parent' => $parentIntegration->id,
                    'meta_key' => 'entity_configuration'
                ],
                [
                    'meta_value' => json_encode($entityConfig),
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId
                ]
            );

            return redirect()->back()->with('success', 'Entity configuration saved successfully!');
        } catch (\Exception $e) {
            Log::error('Error saving entity configuration: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save entity configuration.');
        }
    }

    public function showZohoBooksData($organisationUid, $integrationUid)
    {
        // ✅ Resolve Organisation
        $organisation = null;
        if (class_exists(\Iquesters\Organisation\Models\Organisation::class)) {
            $organisation = \Iquesters\Organisation\Models\Organisation::where('uid', $organisationUid)->firstOrFail();
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

        // ✅ Resolve Integration
        $zohoIntegration = Integration::where('uid', $integrationUid)->firstOrFail();
        $zohoIntegrationUid = $zohoIntegration->uid;

        $orgIntegration = OrganisationIntegration::where('organisation_id', $organisation->id)
            ->where('integration_masterdata_id', $zohoIntegration->id)
            ->first();

        $apiMetaId = null;
        $apiId = [];

        // ✅ Fetch configured API meta IDs
        if ($orgIntegration) {
            $meta = OrganisationIntegrationMeta::where('ref_parent', $orgIntegration->id)
                ->where('meta_key', 'ZB_api_id')
                ->first();

            if ($meta && $meta->meta_value) {
                $configuredApiIds = json_decode($meta->meta_value, true);

                if (is_array($configuredApiIds)) {
                    // Fetch api_list_contacts
                    $apiList = IntegrationMeta::whereIn('id', $configuredApiIds)
                        ->where('meta_key', 'api_list_contacts')
                        ->first();

                    if ($apiList) {
                        $apiMetaId = $apiList->id;
                        $apiId[] = $apiList->id;
                    }

                    // Fetch api_create_a_contact
                    $apiCreate = IntegrationMeta::whereIn('id', $configuredApiIds)
                        ->where('meta_key', 'api_create_a_contact')
                        ->first();

                    if ($apiCreate) {
                        $apiId[] = $apiCreate->id;
                    }
                }
            }
        }

        $hasConfMeta = null;
        $entityName = null;

        if ($orgIntegration) {
            $confMeta = OrganisationIntegrationMeta::where('ref_parent', $orgIntegration->id)
                ->where('meta_key', 'like', 'list_contacts%_conf')
                ->first();

            if ($confMeta && preg_match('/^list_contacts_(.*)_conf$/', $confMeta->meta_key, $matches)) {
                $entityName   = $matches[1];
                $hasConfMeta  = $entityName; // store the actual name instead of true/false
            }
        }

        return view('integration::integrations.zoho_books.data', [
            'organisation'       => $organisation,
            'zohoIntegrationUid' => $zohoIntegrationUid,
            'application'        => $zohoIntegration,
            'apiMetaId'          => $apiMetaId,
            'apiIds'             => $apiId,
            'hasConfMeta'        => $hasConfMeta,
            'entityName'         => $entityName
        ]);
    }
}