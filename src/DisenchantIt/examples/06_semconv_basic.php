<?php

declare(strict_types=1);

/**
 * Example 6 — SemConv (basic): collect + alias through a shared registry.
 *
 * Beyond per-class constants, DisenchantIt can build ONE shared, dependency-free
 * `interface` holding every collected constant. Each class's local constant is
 * then aliased THROUGH to that registry (`private const FOO = SemConv::FOO;`)
 * while its `self::FOO` call sites keep working unchanged.
 */

require __DIR__ . '/bootstrap.php';

hr('rewriteWithSemConv() — shared registry + alias-through');

$code = <<<'PHP'
<?php

namespace Demo;

class User {
    public function role(): string { return 'admin-user'; }
    public function role2(): string { return 'admin-user'; }
    public function guest(): string { return 'guest-user'; }
}
PHP;

code('INPUT', $code);

$result = build()->rewriteWithSemConv($code);

code('OUTPUT — $result->code (class, aliased)', $result->code);
code('OUTPUT — $result->semconvSource (shared registry)', $result->semconvSource);

note(
    "The class still only references its OWN constant (`self::ADMIN_USER`); the constant's value "
    . "now points at the shared `Generated\\SemConv` registry via a `use` import. The registry is "
    . "pure data — no behavior, just name+value pairs referenced by classes."
);
