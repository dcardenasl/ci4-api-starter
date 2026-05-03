<?php

declare(strict_types=1);

namespace Config;

use dcardenasl\CI4Scaffolding\Config\BaseScaffoldingConfig;
use dcardenasl\CI4Scaffolding\Config\ScaffoldingConfig;

class Scaffolding extends BaseScaffoldingConfig
{
    public function build(): ScaffoldingConfig
    {
        return ScaffoldingConfig::defaults();
    }
}
