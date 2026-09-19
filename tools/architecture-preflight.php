<?php

declare(strict_types=1);

/**
 * Lightweight architecture scanner for the RC platform.
 *
 * Usage:
 *   php tools/architecture-preflight.php /path/to/rc-core /path/to/rc-portal ...
 *
 * Business logic for the `my` application is embedded inside a single
 * RC Portal plugin (`rc-portal`), under `modules/{name}/`, sharing the
 * namespace root `RC\Portal\Modules\{Module}\` — there are no separate
 * standalone business plugins to scan per module (decided
 * <2026-09-19>, see rc-core/docs/ARCHITECTURE-OPEN-QUESTIONS.md history).
 * Module-to-module isolation *within* rc-portal is checked by
 * rc-portal's own `tools/preflight.php`, not by this script — this
 * script only checks the boundary Core itself must never cross, plus a
 * few Core-only primitives that must never be used elsewhere.
 *
 * `RC\Catalog\` (the separate `www`-side public projection plugin) is
 * kept in the forbidden-for-Core list because it is still a distinct,
 * undecided concern — see docs/ARCHITECTURE.md §16/§41. Update this list
 * if that decision changes.
 */

$roots = array_slice($argv, 1);
if ($roots === []) {
    $roots = [dirname(__DIR__)];
}

// Namespace roots Core must never reference. `RC\Portal\` matches every
// embedded module too (`RC\Portal\Modules\{Module}\` is a sub-namespace),
// so this single entry covers all of `my`'s business logic in one check.
$forbiddenForCore = ['RC\\Portal\\', 'RC\\Catalog\\'];
$errors = [];

foreach ($roots as $root) {
    $root = realpath($root) ?: $root;
    if (!is_dir($root)) {
        $errors[] = "Missing path: {$root}";
        continue;
    }

    $rootName = basename($root);
    $isCoreRoot = $rootName === 'rc-core' || is_file($root . '/rc-core.php');

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        if (str_ends_with($file->getFilename(), 'preflight.php')) {
            // Every repo's own preflight/scanner tool legitimately contains
            // these forbidden-pattern strings as scan-target data, not as a
            // real reference/call (confirmed empirically: running this
            // script against rc-portal or rc-portal-theme otherwise flags
            // their own tools/preflight.php every time). Scanning a
            // preflight script against itself, or against another repo's
            // preflight script, is a guaranteed false positive — a live
            // example of the "raw text, not an AST" limitation documented
            // in rc-core/AGENTS.md.
            continue;
        }
        $code = file_get_contents($path);
        if (!is_string($code)) {
            continue;
        }

        if ($isCoreRoot || str_contains($code, 'namespace WPRC\\Core')) {
            foreach ($forbiddenForCore as $namespace) {
                if (str_contains($code, $namespace)) {
                    $errors[] = "Core -> higher-layer dependency: {$path} references {$namespace}";
                }
            }
            continue;
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
