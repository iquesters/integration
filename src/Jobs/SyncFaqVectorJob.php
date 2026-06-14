<?php

namespace Iquesters\Integration\Jobs;

use Illuminate\Support\Facades\Http;
use Iquesters\Foundation\Jobs\BaseJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncFaqVectorJob extends BaseJob
{
    protected array $integrationPayload;
    protected ?\Carbon\Carbon $startedAt = null;

    protected function initialize(...$arguments): void
    {
        [$payload] = $arguments;
        $this->integrationPayload = $payload;
    }

    public function process(): void
    {
        try {
            $this->startedAt = now();
            $this->logMethodStart($this->ctx([
                'integration_id'  => $this->integrationPayload['integration_id'] ?? null,
                'integration_uid' => $this->integrationPayload['integration_uid'] ?? null,
            ]));

            $payload = $this->buildFaqPayload();

            $this->logDebug('FAQ vector request payload' . $this->ctx([
                'payload' => $payload,
            ]));

            $response = Http::timeout(0)
                ->withOptions([
                    'connect_timeout' => 10,
                    'read_timeout'    => 0,
                ])
                ->post(
                    'https://api-jobs.iquesters.com/vector/faq/create',
                    $payload
                );

            $this->logInfo(
                'FAQ Vector API response received' . $this->ctx([
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ])
            );

            if (! $response->successful()) {
                $this->logError(
                    'FAQ Vector API call failed' . $this->ctx([
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ])
                );
                return;
            }

            $this->setResponse($response->json());

            $this->logMethodEnd($this->ctx([
                'integration_id'  => $this->integrationPayload['integration_id'] ?? null,
                'integration_uid' => $this->integrationPayload['integration_uid'] ?? null,
            ]));

        } catch (\Throwable $e) {
            $this->logError('FAQ Vector sync failed' . $this->ctx([
                'integration_id'  => $this->integrationPayload['integration_id'] ?? null,
                'integration_uid' => $this->integrationPayload['integration_uid'] ?? null,
                'error'           => $e->getMessage(),
            ]));
            throw $e;
        }
    }

    protected function afterHandle(): void
    {
        parent::afterHandle();

        if (! Schema::hasTable('vector_responses')) {
            return;
        }

        if ($this->getResponse() === null) {
            return;
        }

        try {
            $finishedAt = now();
            $duration = $this->startedAt
                ? abs((int) $this->startedAt->diffInSeconds($finishedAt))
                : null;

            DB::table('vector_responses')->insert([
                'uid'              => (string) Str::ulid(),
                'integration_id'   => $this->integrationPayload['integration_id'] ?? null,
                'job_uuid'         => $this->job?->getJobId(),
                'response'         => json_encode($this->getResponse()),
                'started_at'       => $this->startedAt,
                'finished_at'      => $finishedAt,
                'duration_seconds' => $duration,
                'status'           => 'active',
                'created_by'       => 0,
                'updated_by'       => 0,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

        } catch (\Throwable $e) {
            $this->logWarning('FAQ Vector response insert failed' . $this->ctx([
                'integration_id' => $this->integrationPayload['integration_id'] ?? null,
                'error'          => $e->getMessage(),
            ]));
        }
    }

    private function buildFaqPayload(): array
    {
        return [
            'integration_id'  => $this->integrationPayload['integration_id'] ?? null,
            'integration_uid' => $this->integrationPayload['integration_uid'] ?? null,
            'recreate_flag'   => (bool) ($this->integrationPayload['recreate_flag'] ?? false),
        ];
    }
}