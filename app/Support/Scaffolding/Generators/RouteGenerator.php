<?php

declare(strict_types=1);

namespace App\Support\Scaffolding\Generators;

use App\Support\Scaffolding\ResourceSchema;

/**
 * RouteGenerator
 * Generates or updates the domain-specific route file in app/Config/Routes/v1/.
 */
class RouteGenerator
{
    public function generate(ResourceSchema $schema): array
    {
        $domainKebab = $schema->toKebab($schema->domain);
        $path = APPPATH . "Config/Routes/v1/{$domainKebab}.php";

        $content = file_exists($path) ? (string) file_get_contents($path) : $this->baseTemplate($schema);

        return [
            $path => $this->injectRoute($schema, $content),
        ];
    }

    private function baseTemplate(ResourceSchema $schema): string
    {
        $domainKebab = $schema->toKebab($schema->domain);
        return <<<PHP
<?php

/** @var \CodeIgniter\Router\RouteCollection \$routes */

\$routes->group('{$domainKebab}', ['namespace' => '\App\Controllers\Api\V1\\{$schema->domain}'], function (\$routes) {

    // Auth & Admin Protected Group
    \$routes->group('', ['filter' => ['jwtauth', 'roleauth:admin', 'throttle']], function (\$routes) {
        // Resource routes will be injected here
    });
});
PHP;
    }

    private function injectRoute(ResourceSchema $schema, string $content): string
    {
        $resource = $schema->resource;
        $route = $schema->route;
        $controller = "{$resource}Controller";

        $routeBlock = <<<PHP
        // {$resource} Routes
        \$routes->get('{$route}', '{$controller}::index');
        \$routes->get('{$route}/(:num)', '{$controller}::show/$1');
        \$routes->post('{$route}', '{$controller}::create');
        \$routes->put('{$route}/(:num)', '{$controller}::update/$1');
        \$routes->delete('{$route}/(:num)', '{$controller}::delete/$1');

PHP;

        if (str_contains($content, "{$controller}::index")) {
            return $content; // Already exists
        }

        // Try to inject inside the protected group
        $search = "['filter' => ['jwtauth', 'roleauth:admin', 'throttle']], function (\$routes) {";
        if (str_contains($content, $search)) {
            $pos = strpos($content, $search) + strlen($search);
            return substr($content, 0, $pos) . "\n" . $routeBlock . substr($content, $pos);
        }

        // Fallback: append to end
        return $content . "\n" . $routeBlock;
    }
}
