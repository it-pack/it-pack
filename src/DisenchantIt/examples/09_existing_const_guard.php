<?php

declare(strict_types=1);

/**
 * Example 9 — the self-rewrap guard: an existing constant is not re-promoted.
 *
 * If a class already declares a constant whose value equals a literal that is
 * also used as a magic value, DisenchantIt must NOT treat that declaration's
 * value as a usage site. Otherwise a second pass would corrupt the const into
 * `private const FOO = self::FOO;`. The guard keeps both the existing const and
 * the newly promoted one intact.
 */

require __DIR__ . '/bootstrap.php';

hr('Existing constant guard — no self-rewrap, no duplicate declaration');

$code = <<<'PHP'
<?php

namespace Demo;

class Config {
    private const string KNOWN = 'shared-value';

    public function a(): string { return 'shared-value'; }
    public function b(): string { return 'shared-value'; }
}
PHP;

code('INPUT (already has a const with this value)', $code);

$rewritten = build()->rewrite($code);

code('OUTPUT', $rewritten);

note(
    "The pre-existing `KNOWN` constant keeps its literal value untouched, while the genuine usage "
    . "sites are promoted to a NEW `SHARED_VALUE` constant. The result is idempotent and never "
    . "corrupts a declaration line."
);
