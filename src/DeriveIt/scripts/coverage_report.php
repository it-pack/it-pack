<?php

/**
 * Parses Codeception's Clover coverage XML and reports per-file and overall
 * line-coverage for the shipped source tree, failing (exit 1) when the
 * overall floor is not met.
 *
 * Usage:
 *   php scripts/coverage_report.php [--min=96] [--src=src]
 *
 * The report only considers files under --src (the library surface), not tests
 * or fixtures, so the project floor reflects production code quality.
 */

declare(strict_types=1);

use DOMDocument;
use DOMElement;

$opts = getopt('', ['min:', 'src:', 'xml:']) ?: [];
$min = (float) ($opts['min'] ?? 96);
$src = $opts['src'] ?? 'src';
$xmlPath = $opts['xml'] ?? 'tests/_output/coverage.xml';

if (!is_file($xmlPath)) {
    fwrite(STDERR, "coverage xml not found: {$xmlPath}\n");
    exit(2);
}

$dom = new DOMDocument();
$dom->load($xmlPath);
$project = $dom->documentElement->getElementsByTagName('project')->item(0);
if (!$project instanceof DOMElement) {
    fwrite(STDERR, "malformed coverage xml\n");
    exit(2);
}

$realSrc = realpath($src) ?: $src;
$totalLines = 0;
$totalCovered = 0;
$below = [];

foreach ($project->getElementsByTagName('file') as $file) {
    if (!$file instanceof DOMElement) {
        continue;
    }
    $name = $file->getAttribute('name');
    if (!str_starts_with($name, $realSrc)) {
        continue;
    }
    $metrics = $file->getElementsByTagName('metrics')->item(0);
    if (!$metrics instanceof DOMElement) {
        continue;
    }
    $statements = (int) $metrics->getAttribute('statements');
    $covered = (int) $metrics->getAttribute('coveredstatements');
    if ($statements === 0) {
        continue;
    }
    $pct = $covered / $statements * 100;
    $totalLines += $statements;
    $totalCovered += $covered;
    if ($pct < $min) {
        $below[$name] = $pct;
    }
    printf(
        "%6.2f%%  %d/%d  %s\n",
        $pct,
        $covered,
        $statements,
        str_replace((string) realpath('.'), '', $name),
    );
}

$overall = $totalLines === 0 ? 0.0 : $totalCovered / $totalLines * 100;
echo str_repeat('-', 60) . "\n";
printf("OVERALL (src): %.2f%%  (%d/%d statements covered)\n", $overall, $totalCovered, $totalLines);
echo str_repeat('-', 60) . "\n";

if ($overall < $min) {
    echo "FAIL: overall coverage %.2f%% is below floor %.2f%%\n" . count($below) . " file(s) below threshold.\n";
    exit(1);
}

echo "PASS: overall coverage meets floor of {$min}%.\n";
exit(0);
