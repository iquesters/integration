<?php

namespace Iquesters\Integration\Database\Seeders;

use Iquesters\Foundation\Database\Seeders\BaseSeeder;

class IntegrationSeeder extends BaseSeeder
{
    protected string $moduleName = 'integration';

    protected string $description = 'External system integrations module';

    protected array $metas = [
        'module_icon' => 'fa-solid fa-plug',
        'module_sidebar_menu' => [
            [
                'icon'  => 'fa-solid fa-link',
                'label' => 'Integrations',
                'route' => 'integration.index',
            ],
        ],
    ];

    protected array $entities = [];

    /**
     * Custom seeding logic for Integration module
     */
    protected function seedCustom(): void
    {
        $this->command?->info('🔌 Running Integration module data seeder...');

        // Pass the current Artisan command into the logic seeder
        $logicSeeder = new IntegrationModuleSeeder($this->command);
        $logicSeeder->run();
    }
}