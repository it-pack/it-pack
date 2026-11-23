<?php

declare(strict_types=1);

/**
 * CI threshold check for Unit-only statement coverage.
 *
 * Usage: php tools/coverage-threshold.php [coverage.xml] [threshold=95]
 *
 * Exits 0 when coverage is at or above the threshold, nonzero otherwise.
 * Fails on missing/invalid report, zero denominator, or coverage below the threshold.
 */

$coveragePath = $argv[1] ?? 'tests/_output/coverage.xml';
$rawThreshold = $argv[2] ?? '95';
if (!is_numeric($rawThreshold) || (float)$rawThreshold < 0.0 || (float)$rawThreshold > 100.0) {
    fwrite(STDERR, "FAIL: invalid threshold: $rawThreshold\n");
    exit(1);
}
$threshold = (float)$rawThreshold;

if (!is_file($coveragePath)) {
    fwrite(STDERR, "FAIL: coverage report not found: $coveragePath\n");
    exit(1);
}

/** @noinspection PhpComposerExtensionStubsInspection */
$xml = simplexml_load_string(file_get_contents($coveragePath));
if ($xml === false) {
    fwrite(STDERR, "FAIL: could not parse coverage report: $coveragePath\n");
    exit(1);
}

$project = $xml->project ?? null;
if ($project === null) {
    fwrite(STDERR, "FAIL: coverage report missing <project> element\n");
    exit(1);
}

$metrics = $project->metrics ?? null;
if ($metrics === null) {
    fwrite(STDERR, "FAIL: coverage report missing <metrics> element\n");
    exit(1);
}

$statements = (string)($metrics['statements'] ?? '0');
$covered = (string)($metrics['coveredstatements'] ?? '0');

if ($statements === '0') {
    fwrite(STDERR, "FAIL: zero denominator in coverage report\n");
    exit(1);
}

$statementCount = (int) $statements;
$coveredCount = (int) $covered;
$percentage = $statementCount > 0 ? (100.0 * $coveredCount / $statementCount) : 0.0;

if ($percentage < $threshold) {
    fwrite(STDERR, sprintf(
        "FAIL: Unit statement coverage %.2f%% (%d/%d) below threshold %.1f%%\n",
        $percentage,
        $coveredCount,
        $statementCount,
        $threshold,
    ));
    exit(1);
}

fwrite(STDOUT, sprintf(
    "PASS: Unit statement coverage %.2f%% (%d/%d) meets threshold %.1f%%\n",
    $percentage,
    $coveredCount,
    $statementCount,
    $threshold,
));
exit(0);
