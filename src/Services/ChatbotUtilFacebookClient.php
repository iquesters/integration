<?php

namespace Iquesters\Integration\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Iquesters\Integration\Exceptions\ChatbotUtilFacebookException;

class ChatbotUtilFacebookClient
{
    public function startFacebookConnect(string $integrationId, ?string $displayName): array
    {
        return $this->post('/social/facebook/connect/start', [
            'integration_id' => $integrationId,
            'display_name' => $displayName ?: 'Facebook',
        ], 'Unable to start Facebook onboarding.');
    }

    public function getFacebookPages(string $state): array
    {
        return $this->get('/social/facebook/pages', [
            'state' => $state,
        ], 'Unable to load Facebook pages.');
    }

    public function saveFacebookIntegration(string $state, string $pageId): array
    {
        return $this->post('/social/facebook/integration/save', [
            'state' => $state,
            'page_id' => $pageId,
        ], 'Unable to save Facebook integration.');
    }

    protected function get(string $path, array $query, string $fallbackMessage): array
    {
        $response = $this->request()->get($this->url($path), $query);

        return $this->decodeResponse($response->status(), $response->json(), $fallbackMessage);
    }

    protected function post(string $path, array $payload, string $fallbackMessage): array
    {
        $response = $this->request()->post($this->url($path), $payload);

        return $this->decodeResponse($response->status(), $response->json(), $fallbackMessage);
    }

    protected function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->asJson()
            ->timeout($this->timeout());

        // @todo Add internal auth header after chatbot-util auth contract is finalized.

        return $request;
    }

    protected function decodeResponse(int $status, mixed $payload, string $fallbackMessage): array
    {
        $payload = is_array($payload) ? $payload : [];

        if ($status < 200 || $status >= 300) {
            throw new ChatbotUtilFacebookException(
                $this->safeMessage($payload, $fallbackMessage),
                $status,
                $this->safeString($payload['error_code'] ?? $payload['code'] ?? null),
                $this->safeString($payload['request_id'] ?? null)
            );
        }

        $payload['_util_http_status'] = $status;

        return $payload;
    }

    protected function url(string $path): string
    {
        $baseUrl = trim((string) config('integration.chatbot_util.api_url', ''));

        if ($baseUrl === '') {
            $baseUrl = trim((string) env('CHATBOT_UTIL_API_URL', ''));
        }

        if ($baseUrl === '') {
            Log::warning('chatbot_util_facebook_config_missing', [
                'config_key' => 'CHATBOT_UTIL_API_URL',
            ]);

            throw new ChatbotUtilFacebookException(
                'Facebook setup is temporarily unavailable. Please try again.',
                503,
                'chatbot_util_url_missing'
            );
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    protected function timeout(): int
    {
        $timeout = (int) config('integration.chatbot_util.timeout', 20);

        return $timeout > 0 ? $timeout : 20;
    }

    protected function safeMessage(array $payload, string $fallbackMessage): string
    {
        foreach (['message', 'detail', 'error'] as $key) {
            if (!empty($payload[$key]) && is_string($payload[$key])) {
                return $payload[$key];
            }
        }

        return $fallbackMessage;
    }

    protected function safeString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
