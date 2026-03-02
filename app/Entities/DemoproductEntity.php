<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class DemoproductEntity extends Entity
{
    /** @var array<string, string> */
    protected $casts = [
        'id' => 'integer',
    ];

    /** @var array<int, string> */
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
}
