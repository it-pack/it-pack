<?php

declare(strict_types=1);

// Run separately: php tests/target-policy-coverage.php /path/to/unit-clover.xml
if (!isset($argc, $argv) || $argc !== 2) {
    fwrite(STDERR, "Usage: php tests/target-policy-coverage.php /path/to/unit-clover.xml\n");
    exit(2);
}

$path = $argv[1];
if (!is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "Coverage XML is missing or unreadable: $path\n");
    exit(2);
}

if (!class_exists(DOMDocument::class)) {
    fwrite(STDERR, "The PHP DOM extension is required to read coverage XML.\n");
    exit(2);
}

$previous = libxml_use_internal_errors(true);
$document = new DOMDocument();
try {
    $loaded = $document->load($path, LIBXML_NONET);
} finally {
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
}

if (!$loaded || $document->doctype instanceof DOMDocumentType || $document->documentElement?->tagName !== 'coverage') {
    fwrite(STDERR, "Invalid Clover XML.\n");
    exit(2);
}

$xpath = new DOMXPath($document);
$metrics = $xpath->query('/coverage/project/metrics');
if ($metrics === false || $metrics->length !== 1) {
    fwrite(STDERR, "Expected exactly one Clover project metrics element.\n");
    exit(2);
}

$element = $metrics->item(0);
if (!$element instanceof DOMElement) {
    fwrite(STDERR, "Invalid Clover project metrics element.\n");
    exit(2);
}

$counts = [];
foreach (['statements', 'coveredstatements'] as $attribute) {
    $value = $element->getAttribute($attribute);
    if (preg_match('/^(0|[1-9][0-9]*)$/D', $value) !== 1
        || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
        fwrite(STDERR, "Invalid or missing project metrics attribute: $attribute\n");
        exit(2);
    }

    $counts[$attribute] = (int) $value;
}

$total = $counts['statements'];
$covered = $counts['coveredstatements'];
if ($total === 0 || $covered > $total) {
    fwrite(STDERR, "Invalid statement counts: $covered covered of $total total.\n");
    exit(2);
}

$percent = 100 * ($covered / $total);
if ($covered < $total - intdiv($total, 20)) {
    fwrite(STDERR, sprintf("Statement coverage %.2f%% (%d/%d) is below 95%%.\n", $percent, $covered, $total));
    exit(1);
}

fwrite(STDOUT, sprintf("Statement coverage %.2f%% (%d/%d) meets the 95%% floor.\n", $percent, $covered, $total));
