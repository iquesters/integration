<?php

namespace Iquesters\Integration\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Iquesters\Integration\Services\ChatbotUtilFacebookClient;
use Iquesters\Foundation\Http\Middleware\EnsureTenantResourceAdmin;
use Iquesters\Integration\Tests\Fixtures\TenantAuthorizationFixture;
use Spatie\Permission\Models\Role;

class IntegrationAuthorizationTest extends TenantAuthorizationFixture
{
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);
        require __DIR__.'/../../routes/web.php';
    }

    public function test_facebook_proxy_reads_config_cached_internal_service_token(): void
    {
        // Laravel config:cache can remove .env from request-time lookups.
        // The package must use the cached token, never a raw env fallback.
        config()->set('integration.chatbot_util.api_url', 'https://util.fixture.invalid');
        config()->set('integration.chatbot_util.internal_token', 'cached-test-only');
        Http::fake(['util.fixture.invalid/*' => Http::response(['state' => 'test-state'], 200)]);
        app(ChatbotUtilFacebookClient::class)->startFacebookConnect('A', 'Fixture');
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer cached-test-only'));
    }

    public function test_actual_config_and_credential_routes_are_guarded(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'integrations')) {
                $this->assertContains(EnsureTenantResourceAdmin::class.':integration', $route->middleware());
            }
        }
        $this->actingAs($this->makeUser('user'))->getJson('/integrations/A/configure')->assertForbidden();
        $this->postJson('/integrations/A/get-tokens')->assertForbidden();
        $this->postJson('/social/facebook/connect/start', ['integration_id' => 'A'])->assertForbidden();
    }

    public function test_actual_foreign_resource_cannot_be_edited_or_connected(): void
    {
        $user = $this->makeUser('admin');
        foreach ([1, 2] as $id) {
            DB::table('model_has_organisations')->insert(['organisation_id' => $id, 'model_type' => $user->getMorphClass(), 'model_id' => $user->id]);
        }
        DB::table('organisation_role_assignments')->insert(['organisation_id' => 1, 'model_type' => $user->getMorphClass(),
            'model_id' => $user->id, 'role_id' => Role::findByName('admin', 'web')->id]);
        $this->actingAs($user)->getJson('/integrations/B/configure')->assertNotFound();
        $this->postJson('/social/facebook/connect/start', ['integration_id' => 'B'])->assertNotFound();
        $this->putJson('/integrations/B', ['organisation_id' => 1])->assertNotFound();
        $this->getJson('/integrations/A/apiconf')->assertForbidden();
        $this->postJson('/integrations/A/api/1/save-configuration', ['api_id' => 2])->assertForbidden();
    }
}
