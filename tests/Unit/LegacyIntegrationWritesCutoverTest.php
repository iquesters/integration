<?php

namespace Iquesters\Integration\Tests\Unit;

use Illuminate\Http\Request;
use Iquesters\Integration\Http\Middleware\RequireLegacyIntegrationWrites;
use Orchestra\Testbench\TestCase;

class LegacyIntegrationWritesCutoverTest extends TestCase
{
    public function test_legacy_admin_write_is_allowed_before_cutover(): void
    {
        config()->set('integration.legacy_admin_writes_enabled', true);
        $nextCalled = false;
        $result = (new RequireLegacyIntegrationWrites())->handle(
            Request::create('/integrations', 'POST'),
            function ($request) use (&$nextCalled) {
                $nextCalled = true;
                return response()->json(['ok' => true], 200);
            }
        );

        $this->assertTrue($nextCalled);
        $this->assertSame(200, $result->getStatusCode());
    }

    public function test_legacy_admin_write_is_blocked_after_cutover(): void
    {
        config()->set('integration.legacy_admin_writes_enabled', false);
        $nextCalled = false;
        $result = (new RequireLegacyIntegrationWrites())->handle(
            Request::create('/integrations', 'POST'),
            function ($request) use (&$nextCalled) {
                $nextCalled = true;
                return response()->json(['ok' => true], 200);
            }
        );

        $this->assertFalse($nextCalled);
        $this->assertSame(409, $result->getStatusCode());
        $this->assertSame('legacy_integration_admin_retired', $result->getData(true)['error_code']);
    }
}
