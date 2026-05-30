<?php

namespace Iquesters\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;

class FacebookOAuthFlowTest extends TestCase
{
    public function test_connect_view_uses_same_window_navigation_without_popup_or_redirect_target(): void
    {
        $view = $this->fixture('resources/views/integrations/facebook/config.blade.php');

        $this->assertStringContainsString('window.location.assign(authorizationUrl)', $view);
        $this->assertStringNotContainsString('redirect_target', $view);
        $this->assertStringNotContainsString('postMessage', $view);
        $this->assertStringNotContainsString('window.open', $view);
        $this->assertStringNotContainsString('page_access_token', $view);
        $this->assertStringNotContainsString('user_access_token', $view);
    }

    public function test_completion_view_calls_pages_with_state_only(): void
    {
        $view = $this->fixture('resources/views/integrations/facebook/complete.blade.php');

        $this->assertStringContainsString("url.searchParams.set('state', state)", $view);
        $this->assertStringNotContainsString("url.searchParams.set('integration_id'", $view);
    }

    public function test_completion_view_saves_with_state_and_page_id_only(): void
    {
        $view = $this->fixture('resources/views/integrations/facebook/complete.blade.php');

        $this->assertStringContainsString('state: state', $view);
        $this->assertStringContainsString('page_id: selectedPageId', $view);
        $this->assertStringNotContainsString('integration_id:', $view);
        $this->assertStringNotContainsString('page_access_token', $view);
        $this->assertStringNotContainsString('user_access_token', $view);
        $this->assertStringNotContainsString('code:', $view);
    }

    public function test_proxy_controller_no_longer_requires_integration_id_or_tokens_for_pages_and_save(): void
    {
        $controller = $this->fixture('src/Http/Controllers/IntegrationConfigController.php');

        $this->assertStringContainsString("'state' => 'required|string'", $controller);
        $this->assertStringContainsString("'page_id' => 'required|string'", $controller);
        $this->assertStringNotContainsString("'page_access_token'", $controller);
        $this->assertStringNotContainsString("'user_access_token'", $controller);
        $this->assertStringNotContainsString("'page_name' =>", $controller);
    }

    public function test_completion_route_and_util_client_are_registered_for_state_only_flow(): void
    {
        $routes = $this->fixture('routes/web.php');
        $client = $this->fixture('src/Services/ChatbotUtilFacebookClient.php');

        $this->assertStringContainsString('/social/facebook/connect/complete', $routes);
        $this->assertStringContainsString('social.facebook.connect.complete', $routes);
        $this->assertStringContainsString('getFacebookPages(string $state)', $client);
        $this->assertStringContainsString('saveFacebookIntegration(string $state, string $pageId)', $client);
        $this->assertStringContainsString('@todo Add internal auth header after chatbot-util auth contract is finalized.', $client);
    }

    private function fixture(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $path);

        $this->assertIsString($contents);

        return $contents;
    }
}
