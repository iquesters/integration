<?php

namespace Iquesters\Integration\Config;

use Iquesters\Foundation\Support\BaseConf;
use Iquesters\Foundation\Enums\Module;

class IntegrationConf extends BaseConf
{
    // Inherited property of BaseConf, must initialize
    protected ?string $identifier = Module::INTEGRATION;
    

    protected function prepareDefault(BaseConf $default_values)
    {

    }
}