<?php

declare(strict_types=1);

/**
 * Example 3 — minOccurrences: only promote values repeated "enough".
 *
 * Repetition is the strongest signal that a literal is secretly a shared
 * concept. `minOccurrences` lets you require a literal to appear at least N
 * times before it earns a constant.
 */

require __DIR__ . '/bootstrap.php';

hr('minOccurrences — promote only what is repeated');

$code = <<<'PHP'
<?php

namespace Demo;

class Token {
    public function a(): string { return 'only-once'; }
    public function b(): string { return 'repeated'; }
    public function c(): string { return 'repeated'; }
}
PHP;

code('INPUT', $code);

$all = build()->rewrite($code);          // minOccurrences = 1 (default)
$repeated = build()->rewrite($code, 2);  // only values seen >= 2 times

code('OUTPUT (minOccurrences = 1)', $all);
code('OUTPUT (minOccurrences = 2)', $repeated);

note(
    "With the default threshold both literals are promoted. Raising the threshold to 2 keeps "
    . "'only-once' inline (it appears just once) while 'repeated' still becomes a constant."
);
