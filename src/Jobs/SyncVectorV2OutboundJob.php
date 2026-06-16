<?php

namespace Iquesters\Integration\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Iquesters\Foundation\Jobs\BaseJob;
use Iquesters\Integration\Models\Integration;
use Iquesters\Integration\Support\VectorTelegramNotifier;

class SyncVectorV2OutboundJob extends BaseJob
{
    use VectorTelegramNotifier;

    protected array $payload;

    protected function initialize(...$arguments): void
    {
        [$payload] = $arguments;
        $this->payload = is_array($payload) ? $payload : [];
    }

    protected function process(): void
    {
        try {
            $this->logMethodStart($this->ctx([
                'operation_id' => $this->resolveOperationId(),
            ]));

            $this->setResponse($this->payload);

            $this->logMethodEnd($this->ctx([
                'operation_id' => $this->resolveOperationId(),
            ]));
        } catch (\Throwable $e) {
            $this->logError('Vector v2 outbound process failed' . $this->ctx([
                'operation_id' => $this->resolveOperationId(),
                'error' => $e->getMessage(),
            ]));

            throw $e;
        }
    }

    protected function afterHandle(): void
    {
        parent::afterHandle();

        if (!Schema::hasTable('vector_responses')) {
            return;
        }

        $operationId = $this->resolveOperationId();

        if ($operationId === null) {
            $this->logWarning('SyncVectorV2OutboundJob skipped: missing operation_id');
            return;
        }

        try {
            $integrationUid = (string) (
                data_get($this->payload, 'payload.result.integration_id')
                ?? data_get($this->payload, 'payload.result.integration_uuid')
                ?? data_get($this->payload, 'payload.integration_uuid')
                ?? data_get($this->payload, 'integration_uid')
                ?? data_get($this->payload, 'integration_id')
                ?? ''
            );

            $integrationId = null;

            if ($integrationUid !== '') {
                $integrationId = Integration::query()
                    ->where('uid', $integrationUid)
                    ->value('id');
            }

            $auditUserId = $this->resolveAuditUserId($integrationId);
            $integration = $this->resolveIntegration($integrationId);

            DB::table('vector_responses')->insert([
                'uid' => (string) Str::ulid(),
                'integration_id' => $integrationId,
                'job_uuid' => $this->resolveJobUuid(),
                'operation_id' => $operationId,
                'message' => $this->resolveMessage(),
                'step_status' => $this->resolveStepStatus(),
                'response' => json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'started_at' => now(),
                'finished_at' => now(),
                'duration_seconds' => 0,
                'status' => $this->normalizeStatus(),
                'created_by' => $auditUserId,
                'updated_by' => $auditUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->notifyVectorTelegram($integration, [
                'operation_id' => $this->resolveOperationId(),
                'step_status' => $this->resolveStepStatus(),
                'status' => $this->normalizeStatus(),
                'message' => $this->resolveMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->logError('Vector v2 outbound insert failed' . $this->ctx([
                'operation_id' => $operationId,
                'error' => $e->getMessage(),
            ]));

            throw $e;
        }
    }

    private function resolveOperationId(): ?int
    {
        $operationId = data_get($this->payload, 'operation_id')
            ?? data_get($this->payload, 'payload.operation_id')
            ?? data_get($this->payload, 'payload.result.operation_id');

        return is_numeric($operationId) ? (int) $operationId : null;
    }

    private function resolveMessage(): string
    {
        return (string) (
            data_get($this->payload, 'message')
            ?? data_get($this->payload, 'payload.message')
            ?? 'Vector v2 progress update received.'
        );
    }

    private function resolveStepStatus(): int
    {
        $stepStatus = data_get($this->payload, 'status')
            ?? data_get($this->payload, 'payload.status')
            ?? 1;

        return is_numeric($stepStatus) ? (int) $stepStatus : 1;
    }

    private function normalizeStatus(): string
    {
        $stepStatus = $this->resolveStepStatus();
        
        if (in_array($stepStatus, [0, 1, 2], true)) {
            return 'completed';
        }

        if ($stepStatus === -1) {
            return 'failed';
        }

        return 'processing';
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

    private function resolveAuditUserId(?int $integrationId): int
    {
        $integration = $this->resolveIntegration($integrationId);

        if (!$integration) {
            return 0;
        }

        return (int) ($integration->updated_by ?: $integration->created_by ?: $integration->user_id ?: 0);
    }

    private function resolveIntegration(?int $integrationId): ?Integration
    {
        if (!$integrationId) {
            return null;
        }

        return Integration::query()
            ->select(['id', 'uid', 'name', 'created_by', 'updated_by', 'user_id', 'supported_integration_id'])
            ->with('supportedIntegration')
            ->find($integrationId);
    }

}
