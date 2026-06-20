<?php

namespace Iquesters\Integration\Jobs;

use Illuminate\Support\Facades\Http;
use Iquesters\Foundation\Jobs\BaseJob;
use Iquesters\Foundation\Support\ConfProvider;
use Iquesters\Foundation\Enums\Module;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncFaqVectorJob extends BaseJob
{
    protected array $integrationPayload;
    protected ?\Carbon\Carbon $startedAt = null;
    protected int $operationId;

    protected function initialize(...$arguments): void
    {
        [$payload] = $arguments;
        $this->integrationPayload = $payload;
        $this->operationId = (int) sprintf(
            '%s%02d',
            now()->format('ymdHis'),
            random_int(0, 99)
        );
    }

    public function process(): void
    {
        try {
            $this->startedAt = now();
            $this->logMethodStart($this->ctx([
                'integration_id'  => $this->integrationPayload['integration_id'] ?? null,
                'integration_uid' => $this->integrationPayload['integration_uid'] ?? null,
                'operation_id'    => $this->operationId,
            ]));

            $payload = $this->buildFaqPayload();

            $this->logDebug('FAQ vector request payload' . $this->ctx([
                'payload' => $payload,
            ]));

            $conf = ConfProvider::from(Module::INTEGRATION);
            $baseUrl = rtrim($conf->chatbot_job_url, '/');
            $url = $baseUrl . '/vector/faq/create';

            $this->logInfo('FAQ vector sync calling chatbot-job' . $this->ctx([
                'url' => $url,
            ]));

            $response = Http::timeout(0)
                ->withOptions([
                    'connect_timeout' => 10,
                    'read_timeout'    => 0,
                ])
                ->post($url, $payload);

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
                $this->setResponse([
                    'operation_id'     => $this->operationId,
                    'request_payload'  => $payload,
                    'response_body'    => $response->json() ?? $response->body(),
                ]);
                return;
            }

            $responseData = $response->json();
            if (! is_array($responseData)) {
                $this->logWarning('FAQ Vector API returned non-array response' . $this->ctx([
                    'body' => $response->body(),
                ]));
                $responseData = ['raw' => $response->body()];
            }

            $this->setResponse([
                'operation_id'     => $this->operationId,
                'request_payload'  => $payload,
                'response_body'    => $responseData,
            ]);

            $this->logMethodEnd($this->ctx([
                'integration_id'  => $this->integrationPayload['integration_id'] ?? null,
                'integration_uid' => $this->integrationPayload['integration_uid'] ?? null,
                'operation_id'    => $this->operationId,
            ]));

        } catch (\Throwable $e) {
            $this->logError('FAQ Vector sync failed' . $this->ctx([
                'integration_id'  => $this->integrationPayload['integration_id'] ?? null,
                'integration_uid' => $this->integrationPayload['integration_uid'] ?? null,
                'operation_id'    => $this->operationId,
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

            $triggeredBy = (int) ($this->integrationPayload['triggered_by'] ?? 0);

            DB::table('vector_responses')->insert([
                'uid'              => (string) Str::ulid(),
                'integration_id'   => $this->integrationPayload['integration_id'] ?? null,
                'job_uuid'         => $this->job?->getJobId(),
                'operation_id'     => $this->operationId,
                'response'         => json_encode($this->getResponse(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'started_at'       => $this->startedAt,
                'finished_at'      => $finishedAt,
                'duration_seconds' => $duration,
                'status'           => 'active',
                'created_by'       => $triggeredBy,
                'updated_by'       => $triggeredBy,
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
            'integration_uid' => $this->integrationPayload['integration_uid'] ?? null,
            'recreate_flag'   => (bool) ($this->integrationPayload['recreate_flag'] ?? false),
        ];
    }
}
