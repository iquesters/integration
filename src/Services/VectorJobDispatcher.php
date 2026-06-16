<?php

namespace Iquesters\Integration\Services;

use Iquesters\Integration\Models\Integration;
use Iquesters\Integration\Constants\Constants;
use Iquesters\Integration\Jobs\SyncVectorJob;
use Iquesters\Foundation\Support\ConfProvider;
use Iquesters\Foundation\Enums\Module;
use Iquesters\Foundation\System\Traits\Loggable;
use Iquesters\Integration\Jobs\SyncFaqVectorJob;

class VectorJobDispatcher
{
    use Loggable;

    /**
     * Dispatch vector sync job for a single integration
     */
    public static function dispatchForIntegration(Integration $integration): void
    {
        $logger = new self();

        try {
            $provider = $integration->supportedIntegration;

            if (! $provider) {
                $logger->logWarning('supportedIntegration missing', [
                    'integration_uid' => $integration->uid,
                ]);
                return;
            }

            $supportedProviders = [
                Constants::WOOCOMMERCE,
                // add new ecom providers here later
            ];

            if (! in_array($provider->name, $supportedProviders, true)) {
                $logger->logInfo('Vector sync skipped: unsupported provider' . self::ctx([
                    'integration_uid' => $integration->uid,
                    'provider' => $provider->name,
                ]));
                return;
            }

            $payload = [
                'integration_id' => $integration->id,
                'systems' => [
                    [
                        'integration_provider' => $provider->name,
                        'integration_uuid' => $integration->uid,
                        'recreate_flag' => true,
                    ],
                ],
                'force_cleanup' => true,
            ];

            $logger->logInfo('Dispatching vector sync job' . self::ctx([
                'integration_uid' => $integration->uid,
                'provider' => $provider->name,
            ]));

            $logger->logDebug('Dispatch payload' . self::ctx([
                'payload' => $payload,
            ]));

            SyncVectorJob::dispatch($payload);

        } catch (\Throwable $e) {
            $logger->logError('Failed to dispatch vector job: ' . $e->getMessage() . self::ctx([
                'integration_uid' => $integration->uid ?? null,
            ]));
        }
    }

    /**
     * Dispatch vector sync job for all active WooCommerce integrations
     */
    public static function dispatchForAllActive(): void
    {
        $logger = new self();

        try {
            $conf = ConfProvider::from(Module::INTEGRATION);

            if (! $conf->vector_sync_enabled) {
                $logger->logInfo('Vector sync scheduler disabled via config');
                return;
            }

            $logger->logMethodStart('Scheduled vector dispatch started');

            Integration::with('supportedIntegration')
                ->where('status', Constants::ACTIVE)
                ->whereHas('supportedIntegration', function ($q) {
                    $q->where('name', Constants::WOOCOMMERCE);
                })
                ->chunkById(50, function ($integrations) use ($logger) {

                    foreach ($integrations as $integration) {

                        if ($integration->getMeta('vector_sync_enabled') === '0') {
                            $logger->logInfo('Vector sync disabled for integration' . self::ctx([
                                'integration_uid' => $integration->uid,
                            ]));
                            continue;
                        }

                        self::dispatchForIntegration($integration);
                    }
                });

            $logger->logMethodEnd('Scheduled vector dispatch completed');

        } catch (\Throwable $e) {
            $logger->logError('Scheduled dispatch failed: ' . $e->getMessage());
        }
    }

    /**
     * Dispatch vector sync job for manual trigger
     */
    public static function dispatchManual(Integration $integration): bool
    {
        $logger = new self();

        try {
            $conf = ConfProvider::from(Module::INTEGRATION);

            if (! $conf->vector_sync_manual_allowed) {
                $logger->logWarning('Manual sync blocked by config' . self::ctx([
                    'integration_uid' => $integration->uid,
                ]));
                return false;
            }

            $logger->logInfo('Manual vector sync triggered' . self::ctx([
                'integration_uid' => $integration->uid,
            ]));

            self::dispatchForIntegration($integration);

            return true;

        } catch (\Throwable $e) {
            $logger->logError('Manual dispatch failed: ' . $e->getMessage() . self::ctx([
                'integration_uid' => $integration->uid ?? null,
            ]));

            return false;
        }
    }

    private static function ctx(array $context): string
    {
        return ' | context=' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Dispatch FAQ vector sync job for a single integration
     */
    public static function dispatchFaqForIntegration(
        Integration $integration,
        bool $recreate = false,
        int $triggeredBy = 0
    ): void {
        $logger = new self();

        try {
            $payload = [
                'integration_id'  => $integration->id,
                'integration_uid' => $integration->uid,
                'recreate_flag'   => $recreate,
                'triggered_by'    => $triggeredBy,
            ];

            $logger->logInfo('Dispatching FAQ vector sync job' . self::ctx([
                'integration_uid' => $integration->uid,
                'triggered_by'    => $triggeredBy,
            ]));

            SyncFaqVectorJob::dispatch($payload);

        } catch (\Throwable $e) {
            $logger->logError('Failed to dispatch FAQ vector job: ' . $e->getMessage() . self::ctx([
                'integration_uid' => $integration->uid ?? null,
            ]));
        }
    }

    /**
     * Dispatch FAQ vector sync for all active integrations
     */
    
    public static function dispatchFaqForAllActive(): void
    {
        $logger = new self();

        try {
            $conf = ConfProvider::from(Module::INTEGRATION);

            if (! $conf->faq_vector_sync_enabled) {
                $logger->logInfo('FAQ vector sync scheduler disabled via config');
                return;
            }

            $logger->logMethodStart('Scheduled FAQ vector dispatch started');

            // FAQ vector sync applies to all active integrations regardless of provider type,
            // unlike product sync which is WooCommerce-only. Every integration can have FAQs.
            Integration::where('status', Constants::ACTIVE)
                ->chunkById(50, function ($integrations) use ($logger) {
                    foreach ($integrations as $integration) {
                        if ($integration->getMeta('faq_sync_enabled') === '0') {
                            $logger->logInfo('FAQ sync disabled for integration' . self::ctx([
                                'integration_uid' => $integration->uid,
                            ]));
                            continue;
                        }
                        self::dispatchFaqForIntegration($integration);
                    }
                });

            $logger->logMethodEnd('Scheduled FAQ vector dispatch completed');

        } catch (\Throwable $e) {
            $logger->logError('Scheduled FAQ dispatch failed: ' . $e->getMessage());
        }
    }
}