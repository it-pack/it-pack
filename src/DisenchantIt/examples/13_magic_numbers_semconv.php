<?php

declare(strict_types=1);

/**
 * Example 13 — Magic numbers + SemConv: typed constants in a shared registry.
 *
 * `rewriteWithSemConv()` does everything `rewrite()` does, then collects every
 * promoted constant into ONE shared, dependency-free `interface` and aliases
 * each class constant THROUGH to it. Numeric constants are stored with a TYPE
 * and with their CANONICAL value (so `0x1F` and `31` merge into one concept),
 * while the class keeps the literal's RAW source token.
 */

require __DIR__ . '/bootstrap.php';

$disenchanter = build();

hr('A) Unit-suffix number → typed constant, aliased to SemConv');

$code = <<<'PHP'
<?php

class Session {
    private int $timeoutMs = 5000;

    public function extend(): void {
        $this->timeoutMs = 5000;
    }
}
PHP;

$result = $disenchanter->rewriteWithSemConv($code);

code('INPUT', $code);
code('OUTPUT — $result->code (aliased)', $result->code);
code('OUTPUT — $result->semconvSource (shared registry)', $result->semconvSource);

note(
    "The class references its OWN `self::TIMEOUT_MS`; the constant's value now "
    . "points at `Generated\\SemConv`. The registry declares `public const int "
    . "TIMEOUT_MS = 5000;` — typed, and shared by every class that needs it."
);

hr('B) String + number in one class share one registry');

$code = <<<'PHP'
<?php

class Config {
    private string $env = 'prod';
    private int $timeoutMs = 5000;

    public function isProd(): bool {
        return $this->env === 'prod';
    }
}
PHP;

$result = $disenchanter->rewriteWithSemConv($code);

code('INPUT', $code);
code('OUTPUT — $result->semconvSource', $result->semconvSource);

note(
    "One registry holds both kinds: `public const string ENV = 'prod';` and "
    . "`public const int TIMEOUT_MS = 5000;`. Strings and numbers are promoted "
    . "with the same pipeline and live side by side in the shared vocabulary."
);

hr('C) Raw token vs canonical value — 0x1F → MASK = 0x1F, registry = 31');

$code = <<<'PHP'
<?php

class Bitmask {
    private int $mask = 0x1F;
}
PHP;

$result = $disenchanter->rewriteWithSemConv($code);

code('INPUT', $code);
code('OUTPUT — $result->code (raw token kept)', $result->code);
code('OUTPUT — $result->semconvSource (canonical value)', $result->semconvSource);

note(
    "The class keeps the original literal — `private const int MASK = 0x1F;` — "
    . "but the registry stores the CANONICAL integer: `public const int MASK = 31;`. "
    . "That is why `0x1F` written in one file and `31` written in another merge "
    . "into the same `MASK` concept instead of colliding."
);
