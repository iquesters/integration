<?php

namespace Iquesters\Integration;

use Illuminate\Support\ServiceProvider;
use Iquesters\Foundation\Support\ConfProvider;
use Illuminate\Console\Command;
use Iquesters\Foundation\Enums\Module;
use Illuminate\Support\Facades\Route;
use Iquesters\Integration\Config\IntegrationConf;
use Iquesters\Integration\Database\Seeders\IntegrationSeeder;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        ConfProvider::register(Module::INTEGRATION, IntegrationConf::class);

        // Register the seed command
        $this->registerSeedCommand();
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'integration');
        
        $this->app->instance('app.layout', $this->getAppLayout());
        
        $this->registerAssetRoute();
        
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/integration.php' => config_path('integration.php'),
        ], 'integration-config');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                'command.integration.seed'
            ]);
        }
    }
    
    protected function getAppLayout(): string
    {
        // Try UserInterface first
        if (class_exists(UserInterfaceConf::class)) {
            try {
                $uiConf = ConfProvider::from(Module::USER_INFE);

                if (method_exists($uiConf, 'ensureLoaded')) {
                    $uiConf->ensureLoaded();
                }

                return $uiConf->app_layout;
            } catch (\Throwable $e) {
                Log::warning('Integration: failed to load UserInterface app layout', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback → Integration layout
        return 'integration::layouts.app';
    }

    /**
     * Register the integration seed command.
     */
    protected function registerSeedCommand(): void
    {
        $this->app->singleton('command.integration.seed', function ($app) {
            return new class extends Command {
                protected $signature = 'integration:seed';
                protected $description = 'Seed integration data from the package';

                public function handle()
                {
                    $this->info('Running Integration Seeder...');

                    $seeder = new IntegrationSeeder();
                    $seeder->setCommand($this);
                    $seeder->run();

                    return 0;
                }
            };
        });
    }
    
    protected function registerAssetRoute(): void
    {
        Route::get('/vendor/integration/{path}', function ($path) {
            $filePath = __DIR__ . '/../public/' . $path;

            if (!file_exists($filePath)) {
                abort(404);
            }

            $mimeTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'ico' => 'image/x-icon',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf',
                'eot' => 'application/vnd.ms-fontobject',
            ];

            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

            $cacheControl = in_array($extension, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot'])
                ? 'public, max-age=31536000'
                : 'no-cache';

            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Cache-Control' => $cacheControl,
            ]);
        })->where('path', '.*')->name('integration.asset');
    }

    /**
     * Get the CSS URL for the package
     */
    public static function getCssUrl(string $file = 'css/app.css',bool $defaultcache = true): string
    {
        return route('integration.asset', ['path' => $file]);
    }
    
    /**
     * Get the JS URL for the package
     */
    public static function getJsUrl(string $file = 'js/app.js',bool $defaultcache = true): string
    {
        return route('integration.asset', ['path' => $file]);
    }
    
    /**
     * Get asset URL for the package
     */
    public static function asset(string $path): string
    {
        return route('integration.asset', ['path' => $path]);
    }

}