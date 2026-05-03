<?php

declare(strict_types=1);

namespace Config;

use dcardenasl\CI4ApiCrudMaker\Config\BaseScaffoldingConfig;
use dcardenasl\CI4ApiCrudMaker\Config\ScaffoldingConfig;

class Scaffolding extends BaseScaffoldingConfig
{
    public function build(): ScaffoldingConfig
    {
        return ScaffoldingConfig::defaults();
    }
}
