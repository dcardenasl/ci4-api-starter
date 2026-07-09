<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Removes foreign-namespace permissions that were mirrored under the 'self'
 * application by a Domain app's `domain:sync-permissions --mirror-to-self`.
 *
 * That flag was a workaround for the hub not aggregating permissions across
 * applications: it copied a Domain app's permissions into 'self' (prefixed
 * with the Domain app's code, e.g. `blog.posts.write`) so a hub-only token
 * scope would still contain them. Now that
 * `EffectivePermissionsResolver::resolveAll()` (WBS-BP-08) aggregates
 * permissions across every registered application natively, those mirror
 * copies are redundant and this command lets an operator clean them up.
 *
 * Idempotent: safe to re-run; does nothing if no mirrored permissions exist.
 *
 * Usage:
 *   php spark iam:remove-mirrored-permissions
 *   php spark iam:remove-mirrored-permissions --dry-run
 */
class RemoveMirroredPermissions extends BaseCommand
{
    protected $group = 'IAM';
    protected $name = 'iam:remove-mirrored-permissions';
    protected $description = 'Remove foreign-namespace permission copies from the self application (cleanup after --mirror-to-self).';

    protected $usage = 'iam:remove-mirrored-permissions [--dry-run]';
    protected $options = [
        '--dry-run' => 'Preview what would be removed without making any changes.',
    ];

    public function run(array $params): void
    {
        $dryRun = CLI::getOption('dry-run') === true;

        $db = \Config\Database::connect();

        $selfApp = $db->table('applications')->where('code', 'self')->get()->getRowArray();
        if ($selfApp === null) {
            CLI::write('[iam:remove-mirrored-permissions] Application "self" not found. Nothing to do.', 'yellow');

            return;
        }
        $selfAppId = (int) $selfApp['id'];

        // Collect all non-self app codes -> namespace prefixes.
        $otherApps = $db->table('applications')->where('code !=', 'self')->get()->getResultArray();
        if ($otherApps === []) {
            CLI::write('[iam:remove-mirrored-permissions] No other applications found. Nothing to do.', 'green');

            return;
        }

        $prefixes = array_map(static fn (array $app) => (string) $app['code'] . '.', $otherApps);

        // Find all permissions under 'self' whose code starts with a foreign
        // app's namespace prefix — that is the signature left behind by
        // `domain:sync-permissions --mirror-to-self`, which always prefixes
        // the mirrored code with the source app's code.
        $selfPerms = $db->table('permissions')
            ->where('application_id', $selfAppId)
            ->select('id, code')
            ->get()->getResultArray();

        $toRemove = array_filter($selfPerms, static function (array $perm) use ($prefixes): bool {
            foreach ($prefixes as $prefix) {
                if (str_starts_with((string) $perm['code'], $prefix)) {
                    return true;
                }
            }

            return false;
        });

        if ($toRemove === []) {
            CLI::write('[iam:remove-mirrored-permissions] No mirrored permissions found. Nothing to do.', 'green');

            return;
        }

        $ids = array_values(array_map(static fn (array $p) => (int) $p['id'], $toRemove));

        CLI::write(sprintf('[iam:remove-mirrored-permissions] Found %d mirrored permission(s) under "self":', count($ids)), $dryRun ? 'yellow' : 'red');
        foreach ($toRemove as $perm) {
            CLI::write('  - ' . (string) $perm['code'], 'dark_gray');
        }

        if ($dryRun) {
            CLI::write('[iam:remove-mirrored-permissions] Dry-run: no changes made.', 'yellow');

            return;
        }

        $db->table('role_permissions')->whereIn('permission_id', $ids)->delete();
        $db->table('permissions')->whereIn('id', $ids)->delete();

        Services::effectivePermissionsResolver(false)->invalidateAll();

        CLI::write(sprintf('[iam:remove-mirrored-permissions] Removed %d permission(s) and their role assignments. Cache cleared.', count($ids)), 'green');
    }
}
