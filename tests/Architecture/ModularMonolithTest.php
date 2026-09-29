<?php

/*
|--------------------------------------------------------------------------
| Modular Monolith — architecture tests
|--------------------------------------------------------------------------
|
| Complements deptrac.php with rules that are awkward to express in
| Deptrac (text scanning of migration files, import-level bans for
| non-class code). These tests are part of the enforcement, not advice.
|
*/

test('modules/Shared never imports another module namespace', function () {
    $violations = [];

    foreach (phpFilesUnder('modules/Shared') as $file => $contents) {
        foreach (importedNamespaces($contents) as $import) {
            if (str_starts_with($import, 'Modules\\') && ! str_starts_with($import, 'Modules\\Shared\\')) {
                $violations[] = "$file imports $import";
            }
        }
    }

    expect($violations)->toBe([]);
});

test('no module imports the internal namespace of another module', function () {
    $modules = ['Identity', 'Platform', 'Core'];
    $publicPrefixes = array_map(fn (string $m): string => "Modules\\{$m}\\App\\Contracts\\", $modules);
    $violations = [];

    foreach ($modules as $module) {
        $publicPath = "modules/{$module}/app/Contracts";
        $internalPath = "modules/{$module}";

        foreach (phpFilesUnder($internalPath) as $file => $contents) {
            // Module test suites are glue (same rationale as deptrac's
            // tests/ exclusion): they exercise other modules' factories
            // and models directly by design.
            if (str_contains($file, DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $isPublicFile = str_starts_with($file, baseDir().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $publicPath));

            foreach (importedNamespaces($contents) as $import) {
                if (! str_starts_with($import, 'Modules\\') || str_starts_with($import, "Modules\\{$module}\\")) {
                    continue;
                }

                $isPublicImport = in_array(true, array_map(
                    fn (string $prefix): bool => str_starts_with($import, $prefix),
                    $publicPrefixes,
                ), true);

                // Public surface files may touch Shared and their own
                // module (public traits may delegate to own internals),
                // never other modules.
                if ($isPublicFile) {
                    $ownPrefix = "Modules\\{$module}\\";
                    if (! str_starts_with($import, 'Modules\\Shared\\') && ! str_starts_with($import, $ownPrefix)) {
                        $violations[] = "$file (public surface) imports $import";
                    }

                    continue;
                }

                // Internal code may use other modules' Contracts, never internals.
                if (! $isPublicImport) {
                    $violations[] = "$file imports internal $import";
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

test('the Identity User model is never imported outside the Identity module', function () {
    $forbidden = 'Modules\\Identity\\App\\Domain\\Models\\User';
    $violations = [];

    foreach (['app', 'modules/Shared', 'modules/Core', 'modules/Platform', 'database'] as $dir) {
        foreach (phpFilesUnder($dir) as $file => $contents) {
            if (in_array($forbidden, importedNamespaces($contents), true)) {
                $violations[] = "$file imports $forbidden";
            }
        }
    }

    expect($violations)->toBe([]);
});

test('migrations declare no cross-module foreign key constraints', function () {
    $violations = [];
    $patterns = ['->foreign(', '->constrained(', '->foreignIdFor(', '->foreignUuid('];
    foreach (['database/migrations', 'modules/Shared/database/migrations', 'modules/Identity/database/migrations', 'modules/Core/database/migrations', 'modules/Platform/database/migrations'] as $dir) {
        foreach (phpFilesUnder($dir) as $file => $contents) {
            // Platform's copy of Spatie's permission-tables schema is
            // exempt: every table it references (permissions, roles,
            // model_has_*) is owned by the SAME module — the FKs Spatie
            // requires are same-module by construction. Documented in
            // modules/Platform/CONTRACT.md (Stage 6).
            if (str_contains($file, 'create_permission_tables')) {
                continue;
            }

            foreach (preg_split('/\r?\n/', $contents) ?: [] as $lineNumber => $line) {
                foreach ($patterns as $pattern) {
                    if (str_contains($line, $pattern) && ! str_contains($line, 'tenant')) {
                        $violations[] = sprintf(
                            '%s:%d uses %s — cross-module references must be plain columns (sole exception: tenant_id)',
                            $file, $lineNumber + 1, $pattern,
                        );
                    }
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

test('every module has a filled CONTRACT.md', function () {
    $violations = [];

    foreach (glob(baseDir().DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
        $contract = $moduleDir.DIRECTORY_SEPARATOR.'CONTRACT.md';
        $name = basename($moduleDir);

        if (! is_file($contract)) {
            $violations[] = "modules/{$name} is missing CONTRACT.md";

            continue;
        }

        if (strlen(trim((string) file_get_contents($contract))) < 200) {
            $violations[] = "modules/{$name}/CONTRACT.md looks like a placeholder";
        }
    }

    expect($violations)->toBe([]);
});

test('Spatie packages are only referenced inside the Platform module', function () {
    $violations = [];

    foreach (['app', 'database', 'modules/Shared', 'modules/Identity', 'modules/Core'] as $dir) {
        foreach (phpFilesUnder($dir) as $file => $contents) {
            foreach (importedNamespaces($contents) as $import) {
                if (str_starts_with($import, 'Spatie\\')) {
                    $violations[] = "$file imports $import — Spatie integration belongs to Platform";
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

/**
 * @return array<string, string> absolute path => file contents
 */
function phpFilesUnder(string $relativeDir): array
{
    $base = baseDir().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

    if (! is_dir($base)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
    );

    $files = [];

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
            $files[$file->getPathname()] = (string) file_get_contents($file->getPathname());
        }
    }

    ksort($files);

    return $files;
}

/**
 * @return list<string>
 */
function importedNamespaces(string $contents): array
{
    preg_match_all('/^\s*use\s+([\w\\\\]+)\s*(?:as\s+\w+)?\s*;/m', $contents, $matches);

    return $matches[1];
}

function baseDir(): string
{
    return dirname(__DIR__, 2);
}
