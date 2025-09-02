<?php

namespace Iquesters\Integration\Http\Controllers;

use Illuminate\Routing\Controller;
use Iquesters\Integration\Models\Integration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class IntegrationController extends Controller
{
    /**
     * Display all integrations with their meta data.
     */
    public function index()
    {
        try {
            // Eager load integration_metas for each integration
            $integrations = Integration::with('metas')->get();

            Log::info('IntegrationController@index fetched integrations with metas', [
                'user_id' => Auth::id(),
                'count'   => $integrations->count(),
            ]);

            // Return to Blade view
            return view('integration::integrations.index', compact('integrations'));
        } catch (Exception $e) {
            Log::error('Error fetching integrations with metas', [
                'error'   => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return view('integrations.index')->withErrors('Failed to retrieve integrations.');
        }
    }
}