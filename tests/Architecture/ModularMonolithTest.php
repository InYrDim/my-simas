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
    $modules = ['Identity', 'Core'];
    $publicPrefixes = array_map(fn (string $m): string => "Modules\\{$m}\\App\\Contracts\\", $modules);
    $violations = [];

    foreach ($modules as $module) {
        $publicPath = "modules/{$module}/app/Contracts";
        $internalPath = "modules/{$module}";

        foreach (phpFilesUnder($internalPath) as $file => $contents) {
            $isPublicFile = str_starts_with($file, baseDir().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $publicPath));

            foreach (importedNamespaces($contents) as $import) {
                if (! str_starts_with($import, 'Modules\\') || str_starts_with($import, "Modules\\{$module}\\")) {
                    continue;
                }

                $isPublicImport = in_array(true, array_map(
                    fn (string $prefix): bool => str_starts_with($import, $prefix),
                    $publicPrefixes,
                ), true);

                // Public surface files may only touch Modules\Shared.
                if ($isPublicFile) {
                    if (! str_starts_with($import, 'Modules\\Shared\\')) {
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

test('migrations declare no foreign key constraints', function () {
    $violations = [];
    $patterns = ['->foreign(', '->constrained(', '->foreignIdFor(', '->foreignUuid('];

    foreach (['database/migrations', 'modules/Identity/database/migrations', 'modules/Core/database/migrations', 'modules/Shared/database/migrations'] as $dir) {
        foreach (phpFilesUnder($dir) as $file => $contents) {
            foreach ($patterns as $pattern) {
                if (str_contains($contents, $pattern)) {
                    $violations[] = "$file uses $pattern — cross-module references must be plain columns (no FK)";
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
