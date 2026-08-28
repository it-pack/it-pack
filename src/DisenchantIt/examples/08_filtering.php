<?php

declare(strict_types=1);

/**
 * Example 8 — candidate filtering: what is NOT a magic value.
 *
 * Detection is deliberately conservative. Exception messages, sprintf/printf
 * format strings, backed-enum case values, and prose (anything with spaces or
 * sentence punctuation) are excluded. Only identifier-shaped literals become
 * candidates.
 */

require __DIR__ . '/bootstrap.php';

hr('detect() — see what the filter keeps and drops');

$code = <<<'PHP'
<?php

namespace Demo;

enum Status: string { case OPEN = 'open'; }

class Repo {
    public function kind(): string { return 'create-order'; }            // ✅ identifier -> candidate
    public function isActive(string $s): bool { return $s === 'active'; }  // ✅ identifier -> candidate
    public function boom(): void { throw new \Exception('could not connect'); } // ❌ exception message
    public function msg(): string { return sprintf('User %s deleted', $x); }      // ❌ format string
    public function prose(): string { return 'this is a long sentence'; }  // ❌ not identifier-shaped
}
PHP;

code('INPUT', $code);

$kept = build()->detect($code);

echo 'CANDIDATES SURVIVING THE FILTER:' . PHP_EOL;
if ($kept === []) {
    echo '  (none)' . PHP_EOL;
}
foreach ($kept as $value => $candidate) {
    printf(
        "  • %-16s → const %s (%s)%s",
        "'{$value}'",
        $candidate->constantName,
        $candidate->occurrences[0]->role,
        PHP_EOL,
    );
}
echo PHP_EOL;

note(
    "'open' is an enum backing value, the exception message and format string are noise, and the "
    . "prose fails the identifier-shape check. Only 'create-order' and 'active' remain as candidates."
);
