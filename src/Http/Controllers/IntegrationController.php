<?php

namespace Iquesters\Integration\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Iquesters\Integration\Models\Integration;
use Iquesters\Integration\Models\OrganisationIntegration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
}