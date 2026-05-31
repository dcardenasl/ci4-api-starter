<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\Models\ApplicationModel;
use App\Models\PermissionModel;

/**
 * Allows a domain app (authenticated via X-App-Key) to register its own
 * permissions in the hub without requiring a superadmin JWT.
 *
 * Security: each permission code MUST start with "{app.code}." — a domain app
 * registered as "catalog" can only register "catalog.*" codes. Codes that fail
 * this namespace check are rejected and counted separately.
 */
class SelfPermissionService
{
    public function __construct(
        private readonly PermissionModel $permissionModel,
        private readonly ApplicationModel $applicationModel,
    ) {
    }

    /**
     * @param list<array{code: string, resource: string, action: string, description?: string}> $permissions
     * @return array{created: int, existing: int, rejected: int, errors: list<string>}
     */
    public function sync(int $appId, array $permissions): array
    {
        $application = $this->applicationModel->find($appId);
        if ($application === null) {
            return ['created' => 0, 'existing' => 0, 'rejected' => count($permissions), 'errors' => ['Application not found']];
        }

        $namespace = $application->code . '.';
        $created   = 0;
        $existing  = 0;
        $rejected  = 0;
        $errors    = [];

        foreach ($permissions as $perm) {
            $code = trim((string) ($perm['code'] ?? ''));

            if ($code === '' || !str_starts_with($code, $namespace)) {
                $rejected++;
                $errors[] = "Rejected '{$code}': must start with '{$namespace}'";
                continue;
            }

            $existing_row = $this->permissionModel
                ->where('application_id', $appId)
                ->where('code', $code)
                ->first();

            if ($existing_row !== null) {
                $existing++;
                continue;
            }

            $this->permissionModel->insert([
                'application_id' => $appId,
                'code'           => $code,
                'resource'       => (string) ($perm['resource'] ?? ''),
                'action'         => (string) ($perm['action'] ?? ''),
                'description'    => (string) ($perm['description'] ?? ''),
            ]);
            $created++;
        }

        return compact('created', 'existing', 'rejected', 'errors');
    }
}
