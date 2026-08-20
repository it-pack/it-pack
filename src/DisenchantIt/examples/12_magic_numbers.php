<?php

declare(strict_types=1);

/**
 * Example 12 — Magic numbers: promote numeric literals to typed constants.
 *
 * A bare `5000`, `0x1F` or `3.14` repeated across a class is just as implicit
 * as a bare string. DisenchantIt promotes numeric literals ONLY when a naming
 * hint is recoverable (a unit suffix, a preceding comment, or the variable /
 * property / array-key it is assigned to). The result is a TYPED constant that
 * keeps the literal's raw source token.
 *
 * This example walks through each hint source plus the guardrails (trivial and
 * hint-less numbers are left untouched).
 */

require __DIR__ . '/bootstrap.php';

$disenchanter = build();

hr('1) Unit suffix — $timeoutMs = 5000  →  TIMEOUT_MS');

$code = <<<'PHP'
<?php

class Session {
    private int $timeoutMs = 5000;

    public function extend(): void {
        $this->timeoutMs = 5000;
    }
}
PHP;

code('INPUT', $code);
code('OUTPUT', $disenchanter->rewrite($code));

note(
    "The `ms` unit suffix on `\$timeoutMs` yields `TIMEOUT_MS`. Both occurrences "
    . "(the property default and the assignment) share the hint and collapse onto one constant."
);

hr('2) Preceding comment — // seconds above $cacheTime = 30  →  CACHE_TIME_SECONDS');

$code = <<<'PHP'
<?php

class Cache {
    // seconds
    private int $cacheTime = 30;

    public function window(): int {
        return 30;
    }
}
PHP;

code('INPUT', $code);
code('OUTPUT', $disenchanter->rewrite($code));

note(
    "A `//`/`#` comment on the line(s) immediately ABOVE the literal is used as a "
    . "hint suffix. The bare `return 30;` below has no hint, so it is left alone."
);

hr('3) Variable name — private int $maxRetries = 3  →  MAX_RETRIES');

$code = <<<'PHP'
<?php

class Retry {
    private int $maxRetries = 3;

    public function attempt(): int {
        return 3;
    }
}
PHP;

code('INPUT', $code);
code('OUTPUT', $disenchanter->rewrite($code));

note(
    "With no unit suffix and no comment, the property name itself is the hint. "
    . "`return 3;` still has no hint and is not promoted."
);

hr('4) Raw token preserved — private int $mask = 0x1F  →  MASK = 0x1F');

$code = <<<'PHP'
<?php

class Bitmask {
    private int $mask = 0x1F;

    public function flags(): int {
        return 0x1F;
    }
}
PHP;

code('INPUT', $code);
code('OUTPUT', $disenchanter->rewrite($code));

note(
    "The literal's raw source token is preserved: `0x1F` stays `0x1F` (and "
    . "`1_000_000`, `3.14`, … are likewise kept verbatim). The un-hinted "
    . "`return 0x1F;` is not promoted."
);

hr('5) Guardrails — trivial and hint-less numbers are NOT promoted');

$code = <<<'PHP'
<?php

class Guard {
    public function status(): int {
        return 1;                 // trivial -> not promoted
    }

    public function over(int $n): bool {
        return $n > 42;          // bare number, no naming hint -> not promoted
    }
}
PHP;

code('INPUT', $code);
code('OUTPUT (unchanged)', $disenchanter->rewrite($code));

note(
    "DisenchantIt never invents a name. `0`, `1`, `0.0`, `1.0` are excluded by "
    . "default, and a number with no recoverable hint is left exactly as written."
);
