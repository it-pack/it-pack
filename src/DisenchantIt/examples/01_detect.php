<?php

declare(strict_types=1);

/**
 * Example 1 — detect(): "What magic values does DisenchantIt see?"
 *
 * Before rewriting anything, `detect()` returns the grouped, named candidates.
 * This is the foundation every other feature builds on: it shows you exactly
 * which literals would be promoted and what constant names they would get.
 */

require __DIR__ . '/bootstrap.php';

hr('detect() — inspect the candidates before rewriting');

$code = <<<'PHP'
<?php

namespace Demo;

class Cart {
    public function first(): string { return 'pending'; }
    public function second(): string { return 'pending'; }
    public function third(): string { return 'shipped'; }
}
PHP;

code('INPUT', $code);

$candidates = build()->detect($code);

echo 'DETECTED CANDIDATES (grouped by literal value):' . PHP_EOL;
foreach ($candidates as $value => $candidate) {
    printf(
        "  • value %-12s → const %-14s (%d occurrence%s)%s",
        "'{$value}'",
        $candidate->constantName,
        $candidate->count(),
        $candidate->count() === 1 ? '' : 's',
        PHP_EOL,
    );
}
echo PHP_EOL;

note(
    "Each Candidate knows its value, the generated UPPER_SNAKE_CASE name, and every "
    . "occurrence (class, method, line, role). 'pending' appears twice so it would be "
    . "promoted to a shared constant; 'shipped' once."
);
