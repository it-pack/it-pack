#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Coverage threshold gate for Milestone 3.
 *
 * Parses the PHPUnit-style coverage XML produced by
 * `composer coverage` (codecept run ... --coverage-xml) at
 * tests/_output/coverage.xml and fails when the covered ratio of
 * statements or elements falls below the mandated 95 % floor.
 *
 * Methods are reported informationally only — the project gate is on
 * statements and elements, matching docs/REFACTORING_PLAN.md Milestone 3.
 *
 * Usage: php scripts/coverage_check.php [path-to-coverage.xml]
 */

final class CoverageGate
{
    private const float MIN_RATIO = 0.95;

    private int $statements = 0;
    private int $coveredStatements = 0;
    private int $elements = 0;
    private int $coveredElements = 0;
    private int $methods = 0;
    private int $coveredMethods = 0;

    public function __invoke(string $path): int
    {
        if (!is_file($path)) {
            fwrite(STDERR, "coverage:check — coverage XML not found at {$path}\n");
            fwrite(STDERR, "Run `composer coverage` first.\n");

            return 2;
        }

        $xml = simplexml_load_file($path);
        $files = $xml->xpath('//file');

        foreach ($files as $file) {
            $metrics = $file->xpath('metrics')[0] ?? null;

            if ($metrics === null) {
                continue;
            }

            $this->statements += (int) $metrics['statements'];
            $this->coveredStatements += (int) $metrics['coveredstatements'];
            $this->elements += (int) $metrics['elements'];
            $this->coveredElements += (int) $metrics['coveredelements'];
            $this->methods += (int) $metrics['methods'];
            $this->coveredMethods += (int) $metrics['coveredmethods'];
        }

        $statementRatio = $this->statements === 0 ? 1.0 : $this->coveredStatements / $this->statements;
        $elementRatio = $this->elements === 0 ? 1.0 : $this->coveredElements / $this->elements;
        $methodRatio = $this->methods === 0 ? 1.0 : $this->coveredMethods / $this->methods;

        $ok = $statementRatio >= self::MIN_RATIO && $elementRatio >= self::MIN_RATIO;

        printf(
            "coverage:check — statements %d/%d (%.2f%%) · elements %d/%d (%.2f%%) · methods %d/%d (%.2f%%)\n",
            $this->coveredStatements,
            $this->statements,
            $statementRatio * 100,
            $this->coveredElements,
            $this->elements,
            $elementRatio * 100,
            $this->coveredMethods,
            $this->methods,
            $methodRatio * 100,
        );

        if ($ok) {
            echo "coverage:check — PASS (>= 95% statements & elements)\n";

            return 0;
        }

        $failed = [];
        if ($statementRatio < self::MIN_RATIO) {
            $failed[] = sprintf('statements %.2f%%', $statementRatio * 100);
        }
        if ($elementRatio < self::MIN_RATIO) {
            $failed[] = sprintf('elements %.2f%%', $elementRatio * 100);
        }

        fwrite(STDERR, 'coverage:check — FAIL: ' . implode(', ', $failed) . ' below 95%' . "\n");

        return 1;
    }
}

$path = $argv[1] ?? __DIR__ . '/../tests/_output/coverage.xml';

exit((new CoverageGate())($path));
