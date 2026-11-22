<?php

namespace Iquesters\Integration\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cutover fence for Laravel administration writes replaced by Python B08.
 *
 * Default remains legacy enabled for existing installations. At B08 cutover,
 * set KNORAI_LEGACY_INTEGRATION_WRITES_ENABLED=false in Laravel's environment,
 * rebuild Laravel config cache and verify these routes return 409.
 *
 * This does not disable machine integrations, webhook handlers, scheduler,
 * knob governance, or provider operations not yet migrated to B08.
 */
class RequireLegacyIntegrationWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('integration.legacy_admin_writes_enabled', true)) {
            return response()->json([
                'message' => 'Integration administration has moved to KnorAI.',
                'error_code' => 'legacy_integration_admin_retired',
            ], 409);
        }

        return $next($request);
    }
}
