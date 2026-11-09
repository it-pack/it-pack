<?php
declare(strict_types=1);

const DOWNSTREAM_PACKAGES = ['ConfigureItRunit', 'DeriveItRunit', 'CommitItRunit', 'AsyncIt', 'RunItThirdPartySample'];

function command(array $args, string $cwd, array $env = []): array
{
    $start = microtime(true);
    $out = tmpfile();
    $err = tmpfile();
    $process = proc_open($args, [0 => ['file', '/dev/null', 'r'], 1 => $out, 2 => $err], $pipes, $cwd, $env ?: null);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start ' . $args[0]);
    }
    $code = proc_close($process);
    rewind($out);
    rewind($err);
    return ['command' => $args, 'duration_seconds' => microtime(true) - $start, 'exit_code' => $code, 'stdout' => stream_get_contents($out), 'stderr' => stream_get_contents($err)];
}

function copyTree(string $source, string $target): void
{
    if (!mkdir($target, 0700, true) && !is_dir($target)) {
        throw new RuntimeException("Cannot create $target");
    }
    foreach (new DirectoryIterator($source) as $item) {
        $name = $item->getFilename();
        if ($item->isDot() || in_array($name, ['vendor', 'composer.lock', '.git'], true)) {
            continue;
        }
        $from = $item->getPathname();
        $to = $target . '/' . $name;
        if ($item->isLink()) {
            throw new RuntimeException("Refusing to copy symlink $from");
        }
        if ($item->isDir()) {
            copyTree($from, $to);
        } elseif (!copy($from, $to)) {
            throw new RuntimeException("Cannot copy $from");
        }
    }
}

function removeTree(string $path): void
{
    if (is_link($path) || !is_dir($path)) {
        unlink($path);
        return;
    }
    foreach (new DirectoryIterator($path) as $item) {
        if (!$item->isDot()) {
            removeTree($item->getPathname());
        }
    }
    rmdir($path);
}

function verifyRunIt(string $root, string $checkout, ?string $expectedVersion): array
{
    $autoload = $root . '/vendor/autoload.php';
    if (!is_file($autoload) || realpath($root . '/vendor/it-pack/run-it') !== realpath($checkout)) {
        throw new RuntimeException('Installed RunIt is not the current checkout');
    }
    require $autoload;
    $version = \Composer\InstalledVersions::getPrettyVersion('it-pack/run-it');
    $installPath = \Composer\InstalledVersions::getInstallPath('it-pack/run-it');
    $classFile = (new ReflectionClass(\ItPack\RunIt\Contract\Port\ContextTokenInterface::class))->getFileName();
    $expectedFile = realpath($checkout . '/src/Contract/Port/ContextTokenInterface.php');
    if (!$installPath || realpath($installPath) !== realpath($checkout) || !$classFile || !$expectedFile || realpath($classFile) !== $expectedFile) {
        throw new RuntimeException('RunIt autoload or installation path does not resolve to the checkout');
    }
    if ($expectedVersion !== null && $version !== $expectedVersion) {
        throw new RuntimeException("Unexpected RunIt version: " . ($version ?? '<missing>'));
    }
    if (!$version) {
        throw new RuntimeException('RunIt version is missing');
    }
    return ['version' => $version, 'install_path' => realpath($installPath), 'class_file' => realpath($classFile)];
}

function main(array $argv): int
{
    $options = getopt('', ['package:', 'output:', 'composer:', 'help']);
    if (isset($options['help'])) {
        fwrite(STDOUT, "Usage: php tools/repo/downstream-test.php [--package=NAME] [--output=NEW_FILE] [--composer=PATH]\nRun with the PHP interpreter to test. Default: all five consumers; JSON is written to stdout unless --output is specified.\n");
        return 0;
    }
    $packages = isset($options['package']) ? [$options['package']] : DOWNSTREAM_PACKAGES;
    if (count($argv) !== 1 + count($options) || array_diff($packages, DOWNSTREAM_PACKAGES)) {
        throw new RuntimeException('Invalid arguments or package; use --help and --option=value');
    }
    $handle = null;
    if (isset($options['output'])) {
        $handle = @fopen($options['output'], 'x');
        if (!$handle) {
            throw new RuntimeException('Cannot create output file');
        }
    }
    $repo = dirname(__DIR__, 2);
    $checkout = realpath($repo . '/src/RunIt');
    $git = command(['git', 'status', '--porcelain', '--untracked-files=all'], $repo);
    $revision = command(['git', 'rev-parse', 'HEAD'], $repo);
    if ($git['exit_code'] !== 0 || $revision['exit_code'] !== 0 || !$checkout) {
        throw new RuntimeException('Cannot determine checkout revision or dirty state');
    }
    $composer = $options['composer'] ?? trim((string) shell_exec('command -v composer'));
    if (!is_file($composer)) {
        throw new RuntimeException('Composer executable not found; use --composer=PATH');
    }
    $workspace = sys_get_temp_dir() . '/runit-downstream-' . bin2hex(random_bytes(12));
    if (!mkdir($workspace, 0700)) {
        throw new RuntimeException('Cannot create temporary workspace');
    }
    $results = [];
    try {
        mkdir($workspace . '/bin');
        symlink(PHP_BINARY, $workspace . '/bin/php');
        $env = getenv();
        foreach (array_keys($env) as $key) {
            if (str_starts_with($key, 'COMPOSER_') || $key === 'COMPOSER') {
                unset($env[$key]);
            }
        }
        $env['PATH'] = $workspace . '/bin:' . ($env['PATH'] ?? '');
        $env['COMPOSER_NO_INTERACTION'] = '1';
        foreach ($packages as $package) {
            $result = ['package' => $package, 'status' => 'failed', 'stage' => 'setup', 'steps' => []];
            try {
                $manifest = json_decode(file_get_contents("$repo/src/$package/composer.json"), true, 512, JSON_THROW_ON_ERROR);
                $constraint = $manifest['require']['php'] ?? '';
                $result['php_constraint'] = $constraint;
                if (!preg_match('/^\^8\.(\d+)$/', $constraint, $match)) {
                    throw new RuntimeException("Unsupported PHP constraint syntax: $constraint");
                }
                if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION < (int) $match[1]) {
                    $result['status'] = 'unsupported';
                    $result['stage'] = 'platform';
                    $results[] = $result;
                    continue;
                }
                if (isset($manifest['config']['platform']['php'])) {
                    throw new RuntimeException('PHP platform override is not allowed');
                }
                $base = "$workspace/$package";
                mkdir($base);
                copyTree("$repo/src/$package", "$base/$package");
                foreach (['ConfigureIt', 'DeriveIt'] as $sibling) {
                    if (($package === 'ConfigureItRunit' && $sibling === 'ConfigureIt') || ($package === 'DeriveItRunit' && $sibling === 'DeriveIt')) {
                        copyTree("$repo/src/$sibling", "$base/$sibling");
                    }
                }
                symlink($checkout, "$base/RunIt");
                $root = "$base/$package";
                $repositories = $manifest['repositories'] ?? [];
                $repositories = array_values(array_filter($repositories, static fn ($entry) => !isset($entry['url']) || $entry['url'] !== '../RunIt'));
                $pathRepo = ['type' => 'path', 'url' => '../RunIt', 'options' => ['symlink' => true]];
                $expectedVersion = $package === 'CommitItRunit' ? '1.1.2' : null;
                if ($expectedVersion !== null) {
                    $pathRepo['options']['versions'] = ['it-pack/run-it' => $expectedVersion];
                }
                array_unshift($repositories, $pathRepo);
                $manifest['repositories'] = $repositories;
                file_put_contents("$root/composer.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
                $steps = [
                    'install' => [PHP_BINARY, $composer, 'install', '--no-interaction', '--prefer-dist', '--no-progress'],
                    'platform' => [PHP_BINARY, $composer, 'check-platform-reqs', '--no-interaction'],
                    'verify' => [PHP_BINARY, __FILE__, '--verify', $root, $checkout, $expectedVersion ?? ''],
                    'build' => [PHP_BINARY, "$root/vendor/bin/codecept", 'build'],
                    'test' => [PHP_BINARY, $composer, 'run', 'test', '--no-interaction'],
                ];
                foreach ($steps as $name => $args) {
                    $result['stage'] = $name;
                    $step = command($args, $root, $env);
                    $result['steps'][$name] = $step;
                    if ($step['exit_code'] !== 0) {
                        throw new RuntimeException("$name failed (exit {$step['exit_code']})");
                    }
                }
                $result['verification'] = json_decode($result['steps']['verify']['stdout'], true, 512, JSON_THROW_ON_ERROR);
                $testOutput = $result['steps']['test']['stdout'] . "\n" . $result['steps']['test']['stderr'];
                preg_match_all('/^.*\b(?:skipped|skip)\b.*$/im', $testOutput, $skips);
                $result['skip_lines'] = $skips[0];
                $result['status'] = $skips[0] ? 'passed_with_skips' : 'passed';
                $result['stage'] = 'complete';
            } catch (Throwable $e) {
                $result['error'] = $e->getMessage();
            }
            $results[] = $result;
        }
    } finally {
        removeTree($workspace);
    }
    foreach ($results as $result) {
        fwrite(STDERR, sprintf("%s: %s (%s)%s\n", $result['package'], $result['status'], $result['stage'], isset($result['error']) ? ': ' . $result['error'] : ''));
    }
    $summary = ['php' => ['binary' => PHP_BINARY, 'version' => PHP_VERSION], 'checkout' => ['path' => $checkout, 'revision' => trim($revision['stdout']), 'dirty' => $git['stdout'] !== '', 'status' => $git['stdout']], 'results' => $results];
    $json = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n";
    if ($handle !== null) {
        $written = fwrite($handle, $json);
        $closed = fclose($handle);
        if ($written !== strlen($json) || !$closed) {
            throw new RuntimeException('Cannot write output file');
        }
    } else {
        fwrite(STDOUT, $json);
    }
    $failures = array_filter($results, static fn ($result) => $result['status'] === 'failed' || (isset($options['package']) && $result['status'] === 'unsupported'));
    $executed = array_filter($results, static fn ($result) => $result['status'] !== 'unsupported');
    return $failures || !$executed ? 1 : 0;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        if (($argv[1] ?? '') === '--verify' && count($argv) === 5) {
            fwrite(STDOUT, json_encode(verifyRunIt($argv[2], $argv[3], $argv[4] ?: null), JSON_THROW_ON_ERROR) . "\n");
            exit(0);
        }
        exit(main($argv));
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}
