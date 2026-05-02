<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class AppUserMembershipEntity extends Entity
{
    /** @var array<string, string> */
    protected $datamap = [];

    /** @var list<string> */
    protected $dates = ['created_at', 'updated_at', 'invited_at', 'accepted_at'];

    /** @var array<string, string> */
    protected $casts = [
        'id'             => 'integer',
        'user_id'        => 'integer',
        'application_id' => 'integer',
    ];
}
