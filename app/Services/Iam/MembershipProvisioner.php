<?php

declare(strict_types=1);

namespace App\Services\Iam;

use Config\Database;

/**
 * Auto-provisions an `app_user_memberships` row in the `self` application for
 * any newly created user, regardless of which entry point created them
 * (admin-driven CRUD, public registration, OAuth handler, CLI seeding).
 *
 * The membership starts active with no roles attached — role assignment is a
 * separate, hierarchical decision handled by `AppUserMembershipService`.
 */
class MembershipProvisioner
{
    public const SELF_APPLICATION_ID = 1;

    public function ensureSelfMembership(int $userId, ?string $now = null): void
    {
        $now   = $now ?? date('Y-m-d H:i:s');
        $appId = self::SELF_APPLICATION_ID;
        $db    = Database::connect();

        $exists = $db->table('app_user_memberships')
            ->where('user_id', $userId)
            ->where('application_id', $appId)
            ->countAllResults();

        if ($exists > 0) {
            return;
        }

        $db->table('app_user_memberships')->insert([
            'user_id'        => $userId,
            'application_id' => $appId,
            'status'         => 'active',
            'accepted_at'    => $now,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
    }
}
