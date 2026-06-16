<?php

namespace Iquesters\Integration\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class FacebookConnectCompletionController extends Controller
{
    public function __invoke(Request $request)
    {
        $state = trim((string) $request->query('state', ''));
        $integrationId = trim((string) $request->query('integration_id', ''));

        Log::info('facebook_callback_page_loaded', [
            'user_id' => auth()->id(),
            'state_ref' => $this->stateRef($state),
            'integration_id_present' => $integrationId !== '',
        ]);

        return view('integration::integrations.facebook.complete', [
            'state' => $state !== '' ? $state : null,
            'integrationId' => $integrationId !== '' ? $integrationId : null,
            'initialError' => $state === ''
                ? 'Facebook connection state is missing. Please start the connection again.'
                : null,
            'pagesUrl' => route('social.facebook.pages'),
            'saveUrl' => route('social.facebook.integration.save'),
            'integrationsUrl' => route('integration.index'),
        ]);
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
