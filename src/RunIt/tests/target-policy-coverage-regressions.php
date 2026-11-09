<?php

declare(strict_types=1);

// Run separately: php tests/target-policy-coverage-regressions.php
// Synthetic inputs test the policy only, not suite provenance or attribution.
$cases = [
    'boundary' => [
        '<coverage><project><metrics statements="100" coveredstatements="95"/></project></coverage>', 0, 'meets',
    ],
    'above boundary' => [
        '<coverage><project><metrics statements="100" coveredstatements="96"/></project></coverage>', 0, 'meets',
    ],
    'below boundary' => [
        '<coverage><project><metrics statements="2000" coveredstatements="1899"/></project></coverage>', 1, 'below 95%',
    ],
    'fractional boundary' => [
        '<coverage><project><metrics statements="21" coveredstatements="19"/></project></coverage>', 1, 'below 95%',
    ],
    'malformed XML' => ['<coverage>', 2, 'Invalid Clover XML'],
    'wrong root' => [
        '<other><project><metrics statements="100" coveredstatements="95"/></project></other>', 2, 'Invalid Clover XML',
    ],
    'missing metrics' => ['<coverage><project/></coverage>', 2, 'metrics element'],
    'duplicate metrics' => ['<coverage><project><metrics/><metrics/></project></coverage>', 2, 'metrics element'],
    'missing denominator' => [
        '<coverage><project><metrics coveredstatements="0"/></project></coverage>', 2, 'statements',
    ],
    'missing numerator' => [
        '<coverage><project><metrics statements="100"/></project></coverage>', 2, 'coveredstatements',
    ],
    'zero denominator' => [
        '<coverage><project><metrics statements="0" coveredstatements="0"/></project></coverage>',
        2, 'Invalid statement counts',
    ],
    'invalid denominator' => [
        '<coverage><project><metrics statements="-1" coveredstatements="0"/></project></coverage>', 2, 'statements',
    ],
    'invalid numerator' => [
        '<coverage><project><metrics statements="100" coveredstatements="95.0"/></project></coverage>',
        2, 'coveredstatements',
    ],
    'overflow' => [
        '<coverage><project><metrics statements="999999999999999999999999999"'
            . ' coveredstatements="0"/></project></coverage>',
        2, 'statements',
    ],
    'numerator exceeds denominator' => [
        '<coverage><project><metrics statements="100" coveredstatements="101"/></project></coverage>',
        2, 'Invalid statement counts',
    ],
    'doctype' => [
        '<!DOCTYPE coverage><coverage><project><metrics statements="100" coveredstatements="95"/></project></coverage>',
        2, 'Invalid Clover XML',
    ],
];

$file = tempnam(sys_get_temp_dir(), 'runit-coverage-');
if ($file === false) {
    fwrite(STDERR, "Could not create a temporary coverage file.\n");
    exit(2);
}

$failures = [];
try {
    $cases['missing file'] = [null, 2, 'missing or unreadable'];
    foreach ($cases as $label => [$xml, $expected, $message]) {
        if ($xml === null) {
            $path = $file . '/missing.xml';
        } else {
            if (file_put_contents($file, $xml) === false) {
                throw new RuntimeException('Could not write temporary coverage XML.');
            }

            $path = $file;
        }

        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/target-policy-coverage.php', $path],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start coverage checker.');
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== $expected || !str_contains($stdout . $stderr, $message)) {
            $failures[] = "$label: expected exit $expected and '$message'; got exit $status: $stdout$stderr";
        }
    }
} finally {
    unlink($file);
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("%d synthetic coverage policy controls passed.\n", count($cases)));
