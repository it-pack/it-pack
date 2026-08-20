<?php

declare(strict_types=1);

/**
 * Example 2 — rewrite(): the basic "promote a repeated literal" flow.
 *
 * `rewrite()` runs detect → decide → rewrite on one source string and returns
 * the rewritten source. By default (minOccurrences = 1) every identifier-shaped
 * literal becomes a private const.
 */

require __DIR__ . '/bootstrap.php';

hr('rewrite() — turn repeated literals into private const');

$code = <<<'PHP'
<?php

namespace Demo;

class User {
    public function role(): string { return 'admin-user'; }
    public function label(): string { return 'admin-user'; }
    public function guest(): string { return 'guest-user'; }
}
PHP;

code('INPUT', $code);

$rewritten = build()->rewrite($code);

code('OUTPUT (rewrite)', $rewritten);

note(
    "Each literal is promoted to a `private const string` right after the class opening brace, "
    . "and every usage site now reads `self::CONST`. The constant name is derived deterministically "
    . "from the value (admin-user → ADMIN_USER)."
);
