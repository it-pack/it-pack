<?php

declare(strict_types=1);

/**
 * Example 4 — idempotency: re-running is a safe no-op.
 *
 * DisenchantIt is designed to be run repeatedly (e.g. in a bot that touches a
 * file every commit). Two guarantees make that safe:
 *   1. A constant's own declaration value is never treated as a usage site, so
 *      it is never re-wrapped into `self::X`.
 *   2. Already-declared / already-aliased lines are skipped (no duplicate const).
 */

require __DIR__ . '/bootstrap.php';

hr('Idempotency — rewrite twice, get the same result');

$code = <<<'PHP'
<?php

namespace Demo;

class Widget {
    public function a(): string { return 'widget-kind'; }
    public function b(): string { return 'widget-kind'; }
}
PHP;

code('INPUT', $code);

$first = build()->rewrite($code);
$second = build()->rewrite($first);

code('OUTPUT (first pass)', $first);
code('OUTPUT (second pass)', $second);

if ($first === $second) {
    echo '✅ first pass === second pass — the rewrite is idempotent.' . PHP_EOL . PHP_EOL;
}

note(
    "The constant's declaration line (`private const string WIDGET_KIND = 'widget-kind';`) is "
    . "left intact on the second pass; only genuine usage sites ever become `self::WIDGET_KIND`."
);
