<?php

declare(strict_types=1);

// Run from any directory with: php tools/coverage-attribution/controls.php
// Diagnostic controls describe observed behavior, not a passing isolation gate.
if (!extension_loaded('pcov')) {
    fwrite(STDERR, "PCOV is required.\n");
    exit(2);
}

$directory = __DIR__;
$temporary = sys_get_temp_dir() . '/runit-attribution-' . bin2hex(random_bytes(8));
if (!mkdir($temporary, 0o700) && !is_dir($temporary)) {
    throw new RuntimeException('Could not create temporary directory');
}
$failures = 0;

/** Execute each case independently and retain output on an unexpected result. */
function runControl(string $name, array $command, string $directory, array $environment, callable $check): void
{
    global $failures;
    $pipes = [];
    $descriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]];
    $process = proc_open($command, $descriptors, $pipes, $directory, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException("Could not start $name");
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $status = proc_close($process);
    if (!$check($status, $output)) {
        ++$failures;
        fwrite(STDERR, "FAIL $name (exit $status)\n$output\n");
        return;
    }
    fwrite(STDOUT, "PASS $name (exit $status)\n");
}

$environment = getenv();
$php = [PHP_BINARY, '-d', 'pcov.enabled=1', '-d', 'pcov.directory=.'];
$runner = [...$php, '../../vendor/bin/codecept', 'run', 'Unit'];
foreach ([
    'testTarget' => [1, 0],
    'testDeclaredCollaborator' => [0, 0],
    'testMissingMetadata' => [0, 1],
    'testUndeclaredExecution' => [0, 0],
    'testProviderDiscovery' => [1, 0],
    'testSetupLeak' => [0, 0],
    'testTeardownLeak' => [0, 0],
    'testCoversNothingExecution' => [0, 1],
] as $test => $expectedCoverage) {
    $report = $temporary . '/' . $test . '.xml';
    $marker = $temporary . '/' . $test . '-provider-marker';
    $environment['ATTRIBUTION_PROVIDER_MARKER'] = $marker;
    $environment['ATTRIBUTION_LIFECYCLE_MARKER'] = $marker;
    $command = [
        ...$runner, '--override', 'paths: output: ' . $temporary, '--filter', $test,
        '--coverage', '--coverage-xml=' . $report, '--no-colors',
    ];
    $check = static function (int $status, string $output) use ($test, $report, $marker, $expectedCoverage): bool {
        if ($status !== 0 || !str_contains($output, 'OK (1 test') || !is_file($report)) {
            return false;
        }
        if ($test === 'testProviderDiscovery'
            && (!is_file($marker) || file_get_contents($marker) !== "provider executed\n")) {
            return false;
        }
        $lifecycleMarkers = [
            'testSetupLeak' => 'setup',
            'testTeardownLeak' => 'teardown',
            'testCoversNothingExecution' => 'covers-nothing',
        ];
        if (isset($lifecycleMarkers[$test])
            && (!is_file($marker) || file_get_contents($marker) !== $lifecycleMarkers[$test])) {
            return false;
        }
        $xml = simplexml_load_string(file_get_contents($report));
        if ($xml === false) {
            return false;
        }
        $coverage = [];
        foreach ($xml->project->file as $file) {
            $coverage[basename((string) $file['name'])] = (int) $file->metrics['coveredstatements'];
        }
        return count($coverage) === 2
            && ($coverage['Target.php'] ?? null) === $expectedCoverage[0]
            && ($coverage['Collaborator.php'] ?? null) === $expectedCoverage[1];
    };
    runControl($test, $command, $directory, $environment, $check);
}
foreach (['leak' => 1, 'allowed' => 0] as $case => $expected) {
    $check = static fn (int $status, string $output): bool => $status === $expected
        && str_contains($output, 'Direct control');
    runControl('direct ' . $case, [...$php, 'direct.php', $case], $directory, $environment, $check);
}

fwrite(STDOUT, "Reports and provider marker retained in $temporary\n");
fwrite(STDOUT, 'Codeception accepting the leak and provider controls is an observed enforcement failure, '
    . "not proof of isolation.\n");
fwrite(STDOUT, "Lifecycle leaks discard subject coverage; CoversNothing credits the collaborator.\n");
exit($failures === 0 ? 0 : 1);
