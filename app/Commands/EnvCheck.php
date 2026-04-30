<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class EnvCheck extends BaseCommand
{
    protected $group       = 'API';
    protected $name        = 'env:check';
    protected $description = 'Validate that all required environment variables are set before startup.';
    protected $usage       = 'env:check';

    /**
     * Variables that MUST be set to a non-empty value.
     * Grouped by category for readable output.
     *
     * @var array<string, list<string>>
     */
    private array $required = [
        'Core' => [
            'app.baseURL',
        ],
        'Database' => [
            'database.default.hostname',
            'database.default.database',
            'database.default.username',
        ],
        'Security' => [
            'encryption.key',
            'JWT_SECRET_KEY',
        ],
    ];

    /**
     * Variables recommended in production but optional in development.
     *
     * @var list<string>
     */
    private array $recommended = [
        'CORS_ALLOWED_ORIGINS',
        'EMAIL_FROM_ADDRESS',
        'SENTRY_DSN',
    ];

    public function run(array $params): void
    {
        CLI::write('');
        CLI::write('Checking environment variables...', 'yellow');
        CLI::write('');

        $missing = [];
        $empty   = [];

        foreach ($this->required as $category => $vars) {
            CLI::write("  [{$category}]", 'cyan');
            foreach ($vars as $var) {
                $value = env($var);
                if ($value === null) {
                    CLI::write("    ✗ {$var} — NOT SET", 'red');
                    $missing[] = $var;
                } elseif (trim((string) $value) === '') {
                    CLI::write("    ✗ {$var} — EMPTY", 'red');
                    $empty[] = $var;
                } else {
                    CLI::write("    ✓ {$var}", 'green');
                }
            }
        }

        if (ENVIRONMENT === 'production') {
            CLI::write('');
            CLI::write('  [Recommended (production)]', 'cyan');
            foreach ($this->recommended as $var) {
                $value = env($var);
                if ($value === null || trim((string) $value) === '') {
                    CLI::write("    ! {$var} — not configured", 'yellow');
                } else {
                    CLI::write("    ✓ {$var}", 'green');
                }
            }
        }

        CLI::write('');

        $problems = array_merge($missing, $empty);

        if (empty($problems)) {
            CLI::write('All required environment variables are set.', 'green');
            CLI::write('');
            return;
        }

        CLI::write(count($problems) . ' required variable(s) are missing or empty:', 'red');
        foreach ($problems as $var) {
            CLI::write("  - {$var}", 'red');
        }
        CLI::write('');
        CLI::write('Set them in your .env file before starting the server.', 'yellow');
        CLI::write('');

        exit(1);
    }
}
