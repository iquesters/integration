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

    protected array $entities = [
        'supported_integrations' => [
            'fields' => [],
            'meta_fields' => [
                'icon' => [
                    'meta_key' => 'icon',
                    'type' => 'text',
                    'label' => 'Icon',
                    'required' => false,
                    'nullable' => true,
                    'input_type' => 'textarea',
                ],
                'description' => [
                    'meta_key' => 'description',
                    'type' => 'text',
                    'label' => 'Description',
                    'required' => false,
                    'nullable' => true,
                    'input_type' => 'textarea',
                ],
                'knorai_internal_tool' => [
                    'meta_key' => 'knorai_internal_tool',
                    'type' => 'boolean',
                    'label' => 'Knorai Internal Tool',
                    'required' => false,
                    'nullable' => true,
                    'input_type' => 'checkbox',
                    'default' => true,
                ],
            ],
            'metas' => [],
        ],
        'integrations' => [
            'fields' => [],
            'meta_fields' => [
                'website_url' => [
                    'meta_key' => 'website_url',
                    'type' => 'string',
                    'label' => 'Website URL',
                    'required' => false,
                    'nullable' => true,
                    'input_type' => 'url',
                ],
                'consumer_key' => [
                    'meta_key' => 'consumer_key',
                    'type' => 'string',
                    'label' => 'Consumer Key',
                    'required' => false,
                    'nullable' => true,
                ],
                'consumer_secret' => [
                    'meta_key' => 'consumer_secret',
                    'type' => 'string',
                    'label' => 'Consumer Secret',
                    'required' => false,
                    'nullable' => true,
                ],
                'telegram_chat_id' => [
                    'meta_key' => 'telegram_chat_id',
                    'type' => 'string',
                    'label' => 'Telegram Chat ID',
                    'required' => false,
                    'nullable' => true,
                ],
                'gc_vec_knob' => [
                    'meta_key' => 'gc_vec_knob',
                    'type' => 'number',
                    'label' => 'GC Vec Knob',
                    'required' => false,
                    'nullable' => true,
                    'input_type' => 'number'
                ],
                'human_handover_enabled' => [
                    'meta_key' => 'human_handover_enabled',
                    'type' => 'boolean',
                    'label' => 'Human Handover Enabled',
                    'required' => false,
                    'nullable' => true,
                    'input_type' => 'checkbox',
                ],
            ],
            'metas' => [],
        ],
        'ext_integrations' => [
            'fields' => [],
            'meta_fields' => [],
            'metas' => [],
        ],
    ];

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
