<?php

namespace Iquesters\Integration;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Command;
use Iquesters\Integration\Database\Seeders\IntegrationSeeder;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/integration.php', 'integration');

        // Register the seed command
        $this->registerSeedCommand();
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'integration');

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
}