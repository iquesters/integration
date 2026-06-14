<?php

namespace Iquesters\Integration\Config;

use Iquesters\Foundation\Support\BaseConf;
use Iquesters\Foundation\Enums\Module;

class IntegrationConf extends BaseConf
{
    // Inherited property of BaseConf, must initialize
    protected ?string $identifier = Module::INTEGRATION;
    
    // Vector sync global configuration
    protected bool $vector_sync_enabled;
    protected bool $vector_sync_manual_allowed;
    protected string $vector_sync_schedule_time;
    // FAQ vector sync configuration
    protected bool $faq_vector_sync_enabled;
    protected string $faq_vector_sync_schedule_time;
    
    protected function prepareDefault(BaseConf $default_values)
    {
        // Global switch for vector sync
        $default_values->vector_sync_enabled = true;

        // Allow manual trigger from UI
        $default_values->vector_sync_manual_allowed = true;

        // Daily schedule time (UTC 12 AM)
        $default_values->vector_sync_schedule_time = '10:09';

        // FAQ vector sync
        $default_values->faq_vector_sync_enabled = true;
        $default_values->faq_vector_sync_schedule_time = '11:00';
    }
}