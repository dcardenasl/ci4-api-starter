<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class RoleEntity extends Entity
{
    /** @var array<string, string> */
    protected $datamap = [];

    /** @var list<string> */
    protected $dates = ['created_at', 'updated_at'];

    /** @var array<string, string> */
    protected $casts = [
        'id'             => 'integer',
        'application_id' => '?integer',
        'is_system'      => 'boolean',
    ];
}
