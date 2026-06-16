<?php

namespace Iquesters\Integration\Jobs;

use Illuminate\Support\Facades\Http;
use Iquesters\Foundation\Jobs\BaseJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Iquesters\Integration\Constants\Constants;
use Iquesters\Integration\Models\Integration;
use Iquesters\Integration\Models\SupportedIntegration;
use Iquesters\Integration\Support\VectorTelegramNotifier;

class SyncVectorJob extends BaseJob
{
    use VectorTelegramNotifier;

    protected array $integrationPayload;
    protected ?\Carbon\Carbon $startedAt = null;
    protected ?int $operationId = null;
    protected ?array $apiMeta = null;

    protected function initialize(...$arguments): void
    {
        [$payload] = $arguments;
        $this->integrationPayload = $payload;
    }

    /**
     * Main job handler
     */
    public function process(): void
    {
        try {
            $this->startedAt = now();
            $this->operationId = (int) ($this->integrationPayload['operation_id'] ?? $this->generateOperationId());
            $this->logMethodStart($this->ctx([
                'integration_id' => $this->integrationPayload['integration_id'] ?? null,
                'operation_id' => $this->operationId,
            ]));

            $payload = $this->buildProviderPayload($this->operationId);

            $this->logDebug('Vector request payload' . $this->ctx([
                'payload' => $payload,
            ]));
            
            $request = Http::timeout(160)
                ->withOptions([
                    'connect_timeout' => 10,
                    'read_timeout' => 160,
                ]);

            $response = $request->post($this->getVectorApiUrl(), $payload);

            $this->logInfo(
                'Vector API response received' . $this->ctx([
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'operation_id' => $this->operationId,
                ])
            );

            $responseJson = $response->json();

            $this->apiMeta = [
                'http_status' => $response->status(),
                'successful' => $response->successful(),
                'message' => data_get($responseJson, 'message', $response->body()),
                'step_status' => $this->resolveInitialStepStatus($response, $responseJson),
                'status' => $this->resolveInitialStatus($response, $responseJson),
            ];

            if (! $response->successful()) {
                $status = $response->status();
                $body   = $response->body();

                $this->logError(
                    'Vector API call failed' . $this->ctx([
                        'status' => $status,
                        'body' => $body,
                        'operation_id' => $this->operationId,
                    ])
                );

                $this->setResponse([
                    'operation_id' => $this->operationId,
                    'request_payload' => $payload,
                    'response_body' => $responseJson ?? $body,
                ]);

                return;
            }

            $this->setResponse([
                'operation_id' => $this->operationId,
                'request_payload' => $payload,
                'response_body' => $responseJson,
            ]);

            $this->logMethodEnd($this->ctx([
                'integration_id' => $this->integrationPayload['integration_id'] ?? null,
                'operation_id' => $this->operationId,
            ]));

        } catch (\Throwable $e) {
            $this->logError('Vector sync failed' . $this->ctx([
                'integration_id' => $this->integrationPayload['integration_id'] ?? null,
                'operation_id' => $this->operationId,
                'error' => $e->getMessage(),
            ]));
            throw $e;
        }
    }

    /**
     * Runs after job execution
     */
    protected function afterHandle(): void
    {
        parent::afterHandle();

        if (!Schema::hasTable('vector_responses')) {
            return;
        }

        if ($this->getResponse() === null) {
            return;
        }

        try {
            $finishedAt = now();
            $auditUserId = $this->resolveAuditUserId();
            $integration = $this->resolveIntegration();

            $duration = $this->startedAt
                ? abs((int) $this->startedAt->diffInSeconds($finishedAt))
                : null;

            DB::table('vector_responses')->insert([
                'uid'              => (string) Str::ulid(),
                'integration_id'   => $this->integrationPayload['integration_id'] ?? null,
                'job_uuid'         => $this->resolveJobUuid(),
                'operation_id'     => $this->operationId,
                'message'          => $this->apiMeta['message'] ?? 'Vector v2 request accepted.',
                'step_status'      => $this->apiMeta['step_status'] ?? -1,
                'response'         => json_encode($this->getResponse(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'started_at'       => $this->startedAt,
                'finished_at'      => $finishedAt,
                'duration_seconds' => $duration,
                'status'           => $this->apiMeta['status'] ?? 'failed',
                'created_by'       => $auditUserId,
                'updated_by'       => $auditUserId,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $this->notifyVectorTelegram($integration, [
                'operation_id' => $this->operationId,
                'step_status' => $this->apiMeta['step_status'] ?? -1,
                'status' => $this->apiMeta['status'] ?? 'failed',
                'message' => $this->apiMeta['message'] ?? 'Vector job update',
            ]);

        } catch (\Throwable $e) {
            $this->logWarning('Vector response insert failed' . $this->ctx([
                'integration_id' => $this->integrationPayload['integration_id'] ?? null,
                'error' => $e->getMessage(),
            ]));
        }
    }

    /**
     * Build final vector payload
     */
    private function buildProviderPayload(int $operationId): array
    {
        try {
            $systems = $this->buildSystemsPayload($operationId);

            if (empty($systems)) {
                throw new \RuntimeException('Vector payload has no systems to sync');
            }

            return [
                'systems' => $systems,
                'force_cleanup' => (bool) ($this->integrationPayload['force_cleanup'] ?? false),
            ];

        } catch (\Throwable $e) {
            $this->logError('Failed to build vector payload' . $this->ctx([
                'integration_id' => $this->integrationPayload['integration_id'] ?? null,
                'error' => $e->getMessage(),
            ]));
            throw $e;
        }
    }

    /**
     * Build systems list
     */
    private function buildSystemsPayload(int $operationId): array
    {
        try {
            $items = $this->integrationPayload['systems']
                ?? [$this->integrationPayload];

            if (!is_array($items)) {
                throw new \InvalidArgumentException('Systems payload must be an array');
            }

            $systems = [];

            foreach ($items as $index => $item) {

                if (empty($item['integration_provider'])) {
                    throw new \InvalidArgumentException(
                        "Missing integration_provider at index {$index}"
                    );
                }

                if (empty($item['integration_uuid'])) {
                    throw new \InvalidArgumentException(
                        "Missing integration_uuid at index {$index}"
                    );
                }

                $systems[] = [
                    'system'           => $item['integration_provider'],
                    'integration_uuid' => $item['integration_uuid'], // @todo for now it is uuid but it should be uid
                    'operation_id'     => $operationId,
                    'recreate_flag'    => (bool) ($item['recreate_flag'] ?? false),
                ];
            }

            if (empty($systems)) {
                throw new \RuntimeException('No valid systems found for vector payload');
            }

            return $systems;

        } catch (\Throwable $e) {
            $this->logError('Failed to build systems payload' . $this->ctx([
                'integration_id' => $this->integrationPayload['integration_id'] ?? null,
                'error' => $e->getMessage(),
            ]));
            $this->logDebug('Payload received' . $this->ctx([
                'payload' => $this->integrationPayload,
            ]));
            throw $e;
        }
    }

    private function generateOperationId(): int
    {
        return (int) sprintf('%s%02d', now()->format('ymdHisv'), random_int(0, 99));
    }

    private function resolveJobUuid(): ?string
    {
        if (!$this->job) {
            return null;
        }

        try {
            $payload = json_decode($this->job->getRawBody(), true);

            if (is_array($payload) && !empty($payload['uuid'])) {
                return (string) $payload['uuid'];
            }
        } catch (\Throwable) {
            // Ignore and fall back to the queue job id.
        }

        $jobId = $this->job->getJobId();

        return $jobId !== null ? (string) $jobId : null;
    }

    private function resolveAuditUserId(): int
    {
        $integration = $this->resolveIntegration();

        if (!$integration) {
            return 0;
        }

        return (int) ($integration->updated_by ?: $integration->created_by ?: $integration->user_id ?: 0);
    }

    private function resolveIntegration(): ?Integration
    {
        $integrationId = $this->integrationPayload['integration_id'] ?? null;

        if (!$integrationId) {
            return null;
        }

        return Integration::query()
            ->select(['id', 'created_by', 'updated_by', 'user_id'])
            ->with('supportedIntegration')
            ->find($integrationId);
    }

    private function resolveInitialStepStatus($response, mixed $responseJson): int
    {
        if (! $response->successful()) {
            return -1;
        }

        if ((bool) data_get($responseJson, 'duplicate_suppressed', false)) {
            return 0;
        }

        return 3;
    }

    private function resolveInitialStatus($response, mixed $responseJson): string
    {
        $stepStatus = $this->resolveInitialStepStatus($response, $responseJson);

        return match ($stepStatus) {
            -1 => 'failed',
            0 => 'completed',
            default => 'processing',
        };
    }

    private function getVectorApiUrl(): string
    {
        $provider = $this->resolveSupportedChatbotIntegration();
        $apiUrl = $provider?->getMeta(Constants::VECTOR_API_URL);

        if ($apiUrl) {
            return $apiUrl;
        }

        $this->logError('Vector API URL not found in supported integration meta' . $this->ctx([
            'integration_id' => $this->integrationPayload['integration_id'] ?? null,
            'provider' => Constants::GAUTAMS_CHATBOT,
            'meta_key' => Constants::VECTOR_API_URL,
        ]));

        throw new \RuntimeException('Missing vector API URL for gautams-chatbot provider.');
    }

    private function resolveSupportedChatbotIntegration(): ?SupportedIntegration
    {
        try {
            return SupportedIntegration::query()
                ->with('metas')
                ->where('name', Constants::GAUTAMS_CHATBOT)
                ->first();
        } catch (\Throwable $e) {
            $this->logError('Failed resolving supported chatbot integration for vector API URL' . $this->ctx([
                'integration_id' => $this->integrationPayload['integration_id'] ?? null,
                'provider' => Constants::GAUTAMS_CHATBOT,
                'error' => $e->getMessage(),
            ]));

            return null;
        }
    }
}