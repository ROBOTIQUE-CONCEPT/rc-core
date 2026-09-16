<?php

declare(strict_types=1);

/**
 * Lightweight architecture scanner for RC plugins.
 *
 * Usage:
 *   php tools/architecture-preflight.php /path/to/rc-core /path/to/rc-catalog ...
 */

$roots = array_slice($argv, 1);
if ($roots === []) {
    $roots = [dirname(__DIR__)];
}

$businessModules = [
    'Catalog', 'Leads', 'Products', 'Assets', 'Interventions', 'Inventory',
    'Quotes', 'Contacts', 'Orders', 'Invoices', 'Projects', 'Tasks', 'Deliveries',
];
$businessNamespaces = array_map(static fn (string $module): string => 'WPRC\\' . $module . '\\', $businessModules);
$portalNamespace = 'WPRC\\Portal\\';
$errors = [];

foreach ($roots as $root) {
    $root = realpath($root) ?: $root;
    if (!is_dir($root)) {
        $errors[] = "Missing path: {$root}";
        continue;
    }

    $rootName = basename($root);
    $rootModule = null;
    foreach (array_merge($businessModules, ['Portal']) as $module) {
        if ($rootName === 'rc-' . strtolower($module)) {
            $rootModule = $module;
            break;
        }
    }
    $isCoreRoot = $rootName === 'rc-core' || is_file($root . '/rc-core.php');

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $code = file_get_contents($path);
        if (!is_string($code)) {
            continue;
        }

        if ($isCoreRoot || str_contains($code, 'namespace WPRC\\Core')) {
            foreach (array_merge($businessNamespaces, [$portalNamespace]) as $namespace) {
                if (str_contains($code, $namespace)) {
                    $errors[] = "Core -> higher-layer dependency: {$path} references {$namespace}";
                }
            }
            continue;
        }

        if ($rootModule !== null) {
            foreach ($businessModules as $module) {
                if ($module === $rootModule) {
                    continue;
                }
                $namespace = 'WPRC\\' . $module . '\\';
                if (str_contains($code, $namespace)) {
                    $errors[] = ($rootModule === 'Portal' ? 'Portal -> business-module dependency' : 'Business module -> business module dependency') . ": {$path} references {$namespace}";
                }
            }
            // RC Portal is the only allowed higher-layer dependency for business
            // modules. It owns all rendering, CSS and browser behavior on `my`.
            // Portal itself remains forbidden from importing business modules.
        }

        if (preg_match('/\\bwp_remote_(get|post|request|head)\\s*\\(/', $code) === 1) {
            $errors[] = "Direct HTTP call outside Core: {$path}";
        }

        if (preg_match('/\\b(add_role|remove_role)\\s*\\(/', $code) === 1) {
            $errors[] = "Direct role mutation outside Core: {$path}";
        }

        // Site switching is a Core concern. A narrowly-scoped CLI migration is
        // allowed to use SiteContext, but direct switch_to_blog() is forbidden.
        if (preg_match('/\\bswitch_to_blog\\s*\\(/', $code) === 1) {
            $errors[] = "Direct site switching outside Core: {$path}";
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "RC architecture preflight FAILED\n\n" . implode("\n", $errors) . "\n");
    exit(1);
}

echo "RC architecture preflight OK\n";
