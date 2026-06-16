<?php

namespace Iquesters\Integration\Support;

use Illuminate\Support\Facades\Http;
use Iquesters\Integration\Models\Integration;

trait VectorTelegramNotifier
{
    protected function notifyVectorTelegram(?Integration $integration, array $context): void
    {
        if (!$integration) {
            $this->logWarning('Telegram notification skipped because integration was not resolved' . $this->ctx([
                'operation_id' => $context['operation_id'] ?? null,
            ]));
            return;
        }

        $chatId = $integration->getMeta('telegram_chat_id');

        if (empty($chatId)) {
            $this->logWarning('Telegram notification skipped because telegram_chat_id meta is missing' . $this->ctx([
                'operation_id' => $context['operation_id'] ?? null,
                'integration_uid' => $integration->uid,
            ]));
            return;
        }

        $message = implode("\n", [
            'operation_id: ' . ($context['operation_id'] ?? '-'),
            'step_status: ' . ($context['step_status'] ?? '-'),
            'message: ' . ($context['message'] ?? 'Vector job update'),
        ]);

        try {
            $response = Http::acceptJson()->post(
                'https://api-util.iquesters.com/telegram/send?chat_id=' .
                urlencode((string) $chatId) .
                '&message=' .
                urlencode($message),
                []
            );

            if (!$response->successful()) {
                $this->logWarning('Telegram notification failed' . $this->ctx([
                    'operation_id' => $context['operation_id'] ?? null,
                    'integration_uid' => $integration->uid,
                    'chat_id' => $chatId,
                    'response_status' => $response->status(),
                ]));
                return;
            }

            $this->logInfo('Telegram notification sent' . $this->ctx([
                'operation_id' => $context['operation_id'] ?? null,
                'integration_uid' => $integration->uid,
                'chat_id' => $chatId,
            ]));
        } catch (\Throwable $e) {
            $this->logError('Telegram notification error' . $this->ctx([
                'operation_id' => $context['operation_id'] ?? null,
                'integration_uid' => $integration->uid,
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]));
        }
    }
}
