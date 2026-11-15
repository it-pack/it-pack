<?php

declare(strict_types=1);

use ItPack\DisenchantIt\Factory\DetectorFactory;
use ItPack\DisenchantIt\ValueObject\Source\SourceCode;

// Read-only inventory. Run with --help for policy and exit-code documentation.
try {
    if ($argc === 2 && $argv[1] === '--help') {
        fwrite(STDOUT, <<<'HELP'
Usage: php magic-values.php AUTOLOAD POLICY [--json] [--root=DIR]

AUTOLOAD is a trusted Composer autoloader containing DisenchantIt 1.1 and its
dependencies, running on PHP 8.5+. POLICY is JSON, relative to its directory:
{"version":1,"include":["src"],"exclude":["src/generated"],"min_occurrences":1,
 "mappings":[{"path":"src/A.php","line":12,"kind":"string",
 "sha256":"<sha256 of raw literal token>","symbol":"\\Example\\Names::VALUE"}]}

Paths are relative to POLICY's directory unless --root specifies a directory.
Includes and excludes are literal relative file or directory paths, not globs.
vendor, generated, fixtures and .git directories are always excluded; symlinks
are rejected when encountered, but concurrent tree changes can race these checks.
Scan only trusted, stable trees. Raw scanner results include diagnostics, definitions and trivial
values, not necessarily actionable magic values. Interpolated strings and other
scanner omissions are not inventoried. Output never includes literal contents;
hashes can still reveal low-entropy values. Aggregates group identical raw tokens,
kind, scanner role and hashed scanner naming hint; they do not prove semantic
equivalence. min_occurrences filters groups by total occurrences, default 1.
Mappings are validated against all scanned occurrences before filtering, and are unverified suggestions,
not fixes. No source is executed or modified; fix/apply is not supported.
Exit: 0 no findings meeting threshold, 1 findings, 2 configuration, dependency,
parse or I/O error. A clean result is not proof that no magic values exist.

From the repository root, install and run the nonblocking RunIt pilot with:
  composer install --working-dir=tools/repo/magic-values-composer
  php tools/repo/magic-values.php tools/repo/magic-values-composer/vendor/autoload.php tools/repo/magic-values-runit.json --root=.
The repository ignores Composer lockfiles; resolution is not pinned across installs.
HELP);
        fwrite(STDOUT, "\n");
        exit(0);
    }
    if ($argc < 3 || $argc > 5) {
        throw new RuntimeException('Expected AUTOLOAD POLICY [--json] [--root=DIR]; use --help');
    }
    $json = false;
    $rootOption = null;
    foreach (array_slice($argv, 3) as $option) {
        if ($option === '--json' && !$json) {
            $json = true;
        } elseif (str_starts_with($option, '--root=') && $rootOption === null && strlen($option) > 7) {
            $rootOption = substr($option, 7);
        } else {
            throw new RuntimeException('Invalid or duplicate option');
        }
    }
    if (PHP_VERSION_ID < 80500 || !is_file($argv[1]) || !is_readable($argv[1])) {
        throw new RuntimeException('PHP 8.5+ and a readable trusted Composer autoloader are required');
    }
    require $argv[1];
    if (!class_exists(DetectorFactory::class)) {
        throw new RuntimeException('DisenchantIt is not available from the autoloader');
    }
    $policyFile = realpath($argv[2]);
    if ($policyFile === false || !is_file($policyFile) || !is_readable($policyFile)) {
        throw new RuntimeException('Cannot read policy');
    }
    $policyText = file_get_contents($policyFile, false, null, 0, 1048577);
    if ($policyText === false || strlen($policyText) > 1048576) {
        throw new RuntimeException('Cannot read policy or policy exceeds 1 MiB');
    }
    $policy = json_decode($policyText, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($policy) || array_diff(array_keys($policy), ['version', 'include', 'exclude', 'mappings', 'min_occurrences']) || ($policy['version'] ?? null) !== 1 || !isset($policy['include']) || !is_array($policy['include']) || !array_is_list($policy['include']) || !$policy['include'] || (array_key_exists('min_occurrences', $policy) && (!is_int($policy['min_occurrences']) || $policy['min_occurrences'] < 1))) {
        throw new RuntimeException('Invalid policy fields, version or include list');
    }
    foreach (['exclude', 'mappings'] as $key) {
        if (array_key_exists($key, $policy) && (!is_array($policy[$key]) || !array_is_list($policy[$key]))) {
            throw new RuntimeException("$key must be a list");
        }
    }
    $validPath = static fn ($p): bool => is_string($p) && preg_match('~^(?!/)(?!.*(?:^|/)\.\.?(/|$))(?!.*//)[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*$~D', $p) === 1;
    foreach (array_merge($policy['include'], $policy['exclude'] ?? []) as $path) {
        if (!$validPath($path)) {
            throw new RuntimeException('Paths must be normalized relative paths');
        }
    }
    $mappings = [];
    foreach ($policy['mappings'] ?? [] as $mapping) {
        if (!is_array($mapping) || count($mapping) !== 5 || array_diff(array_keys($mapping), ['path', 'line', 'kind', 'sha256', 'symbol']) || !$validPath($mapping['path'] ?? null) || !is_int($mapping['line'] ?? null) || $mapping['line'] < 1 || !in_array($mapping['kind'] ?? null, ['string', 'int', 'float'], true) || !is_string($mapping['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $mapping['sha256']) || !is_string($mapping['symbol'] ?? null) || !preg_match('/^\\\\[A-Za-z_][A-Za-z0-9_\\\\]*::[A-Za-z_][A-Za-z0-9_]*$/D', $mapping['symbol'])) {
            throw new RuntimeException('Invalid mapping');
        }
        $key = implode(':', [$mapping['path'], $mapping['line'], $mapping['kind'], $mapping['sha256']]);
        if (isset($mappings[$key])) {
            throw new RuntimeException('Duplicate mapping');
        }
        $mappings[$key] = $mapping['symbol'];
    }
    $root = $rootOption === null ? dirname($policyFile) : realpath($rootOption);
    if ($root === false || !is_dir($root) || !is_readable($root)) {
        throw new RuntimeException('Invalid root directory');
    }
    $files = [];
    $excluded = static function (string $path) use ($policy): bool {
        foreach (explode('/', $path) as $segment) {
            if (in_array(strtolower($segment), ['vendor', 'generated', '_generated', 'fixtures', 'fixture', '.git'], true)) {
                return true;
            }
        }
        foreach ($policy['exclude'] ?? [] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }
        return false;
    };
    $walk = static function (string $path) use (&$walk, &$files, $root, $excluded): void {
        if ($excluded($path)) {
            return;
        }
        $full = $root . '/' . $path;
        if (is_link($full)) {
            throw new RuntimeException("Symlink rejected: $path");
        }
        if (is_dir($full)) {
            $entries = scandir($full);
            if ($entries === false) {
                throw new RuntimeException("Cannot list: $path");
            }
            foreach (array_diff($entries, ['.', '..']) as $entry) {
                if (preg_match('/^[A-Za-z0-9_.-]+$/D', $entry) !== 1) {
                    throw new RuntimeException('Unsupported filename in selected directory');
                }
                $walk($path . '/' . $entry);
            }
        } elseif (is_file($full)) {
            if (str_ends_with($path, '.php')) {
                $files[$path] = $full;
            }
        } else {
            throw new RuntimeException("Missing or unsupported path: $path");
        }
    };
    foreach ($policy['include'] as $path) {
        // Reject symlinks in parent components too.
        $parts = explode('/', $path);
        $prefix = '';
        foreach ($parts as $part) {
            $prefix .= ($prefix === '' ? '' : '/') . $part;
            if (is_link($root . '/' . $prefix)) {
                throw new RuntimeException("Symlink rejected: $prefix");
            }
        }
        $walk($path);
    }
    if ($files === []) {
        throw new RuntimeException('No PHP files selected');
    }
    ksort($files, SORT_STRING);
    $detector = DetectorFactory::create()->build();
    $findings = [];
    $matched = [];
    foreach ($files as $path => $file) {
        if (!is_readable($file) || ($size = filesize($file)) === false || $size > 1048576) {
            throw new RuntimeException("Unreadable or oversized source: $path");
        }
        $source = file_get_contents($file, false, null, 0, 1048577);
        if ($source === false || strlen($source) > 1048576) {
            throw new RuntimeException("Cannot read source: $path");
        }
        try {
            $literals = $detector->scanSource(new SourceCode($source));
        } catch (Throwable $error) {
            throw new RuntimeException("Cannot parse or scan source: $path", 0, $error);
        }
        foreach ($literals as $literal) {
            $hash = hash('sha256', $literal->raw);
            $key = implode(':', [$path, $literal->line, $literal->kind, $hash]);
            if (isset($mappings[$key])) {
                $matched[$key] = ($matched[$key] ?? 0) + 1;
            }
            $hint = $literal->namingContext?->signature();
            $findings[] = ['path' => $path, 'line' => $literal->line, 'kind' => $literal->kind, 'role' => $literal->role->value, 'sha256' => $hash, 'hint_sha256' => $hint === null ? null : hash('sha256', $hint), 'symbol' => $mappings[$key] ?? null];
        }
    }
    foreach ($mappings as $key => $_symbol) {
        if (($matched[$key] ?? 0) !== 1) {
            throw new RuntimeException('Mapping is stale or matches multiple literals: ' . $key);
        }
    }
    $groupKey = static fn (array $finding): string => json_encode([$finding['kind'], $finding['role'], $finding['sha256'], $finding['hint_sha256']], JSON_THROW_ON_ERROR);
    $counts = [];
    foreach ($findings as $finding) {
        $key = $groupKey($finding);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
    $findings = array_values(array_filter($findings, static fn ($finding) => $counts[$groupKey($finding)] >= ($policy['min_occurrences'] ?? 1)));
    usort($findings, static fn ($a, $b) => [$a['path'], $a['line'], $a['kind'], $a['sha256'], $a['role'], $a['hint_sha256']] <=> [$b['path'], $b['line'], $b['kind'], $b['sha256'], $b['role'], $b['hint_sha256']]);
    $groups = [];
    foreach ($findings as $finding) {
        $key = $groupKey($finding);
        if (!isset($groups[$key])) {
            $groups[$key] = ['kind' => $finding['kind'], 'role' => $finding['role'], 'sha256' => $finding['sha256'], 'hint_sha256' => $finding['hint_sha256'], 'occurrences' => 0, 'files' => []];
        }
        ++$groups[$key]['occurrences'];
        $groups[$key]['files'][$finding['path']] = true;
    }
    ksort($groups, SORT_STRING);
    foreach ($groups as &$group) {
        $group['files'] = array_keys($group['files']);
        sort($group['files'], SORT_STRING);
    }
    unset($group);
    if ($json) {
        $output = json_encode(['version' => 1, 'findings' => $findings, 'groups' => array_values($groups)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    } else {
        $output = '';
        foreach ($findings as $finding) {
            $output .= sprintf("%s:%d %s %s sha256:%s occurrences:%d%s\n", $finding['path'], $finding['line'], $finding['kind'], $finding['role'], $finding['sha256'], $counts[$groupKey($finding)], $finding['symbol'] === null ? '' : ' suggested ' . $finding['symbol']);
        }
    }
    if ($output !== '' && fwrite(STDOUT, $output) !== strlen($output)) {
        throw new RuntimeException('Cannot write report');
    }
    exit($findings ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, 'magic-values: ' . $error->getMessage() . "\n");
    exit(2);
}
