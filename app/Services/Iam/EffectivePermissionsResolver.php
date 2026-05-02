<?php

declare(strict_types=1);

namespace App\Services\Iam;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Resolves the set of effective permission codes a user has within an
 * application by walking memberships → roles → role_permissions → permissions.
 *
 * Results are cached for 60 seconds. Use invalidateForUser() after mutations to
 * memberships or membership_roles for a specific user; invalidateAll() when
 * role/permission mappings change globally.
 */
class EffectivePermissionsResolver
{
    private const CACHE_TTL = 60;

    /**
     * @param ConnectionInterface<object, object> $db
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @return list<string> permission codes (sorted, deduplicated)
     */
    public function resolve(int $userId, int $applicationId): array
    {
        $cacheKey = self::cacheKey($userId, $applicationId);

        /** @var list<string>|null $cached */
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $codes = $this->load($userId, $applicationId);
        $this->cache->save($cacheKey, $codes, self::CACHE_TTL);

        return $codes;
    }

    public function invalidateForUser(int $userId, int $applicationId): void
    {
        $this->cache->delete(self::cacheKey($userId, $applicationId));
    }

    /**
     * Bulk invalidation: for cases where role/permission mappings change and
     * affect potentially many users at once.
     */
    public function invalidateAll(): void
    {
        $this->cache->deleteMatching('iam_eff_perms_*');
    }

    /**
     * @return list<string>
     */
    private function load(int $userId, int $applicationId): array
    {
        $query = $this->db->table('app_user_memberships m')
            ->select('p.code')
            ->distinct()
            ->join('membership_roles mr', 'mr.membership_id = m.id')
            ->join('roles r', 'r.id = mr.role_id')
            ->join('role_permissions rp', 'rp.role_id = r.id')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('m.user_id', $userId)
            ->where('m.application_id', $applicationId)
            ->where('m.status', 'active')
            ->where('p.application_id', $applicationId)
            ->orderBy('p.code', 'ASC')
            ->get();

        if ($query === false) {
            return [];
        }

        $rows = $query->getResultArray();

        $codes = array_map(static fn (array $row) => (string) $row['code'], $rows);

        return array_values(array_unique($codes));
    }

    private static function cacheKey(int $userId, int $applicationId): string
    {
        return "iam_eff_perms_{$userId}_{$applicationId}";
    }
}
