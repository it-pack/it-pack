<?php

declare(strict_types=1);

/**
 * Example 5 — placement strategies: where should the constant live?
 *
 * Given the occurrences of one value, the placement strategy decides where the
 * promoted constant is declared:
 *   • SameClass — all occurrences in ONE class → one const on that class.
 *                 (If a value spans several classes, SameClass does nothing —
 *                  that is not its job.)
 *   • PerClass  — occurrences span several classes → each class gets its own
 *                 same-named const (no shared abstraction invented).
 *   • Auto      — picks SameClass vs PerClass automatically (the default).
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DisenchantIt\Factory\ConstantPlacementStrategyFactory;

$code = <<<'PHP'
<?php

namespace Demo;

class Order {
    public function status(): string { return 'pending'; }
}

class Invoice {
    public function status(): string { return 'pending'; }
}
PHP;

code('INPUT (same literal used in TWO classes)', $code);

$strategies = [
    'Auto (default)' => null,
    'PerClass'       => ConstantPlacementStrategyFactory::create()->buildPerClass(),
    'SameClass'      => ConstantPlacementStrategyFactory::create()->buildSameClass(),
];

foreach ($strategies as $label => $strategy) {
    $disenchanter = buildCustom($strategy);
    $out = $disenchanter->rewrite($code);
    code("OUTPUT — strategy: {$label}", $out);
}

note(
    "Auto and PerClass both emit one `private const string PENDING` per class (the literal spans "
    . "two classes). SameClass refuses to span classes, so it emits nothing and the source is left "
    . "untouched — exactly the documented behavior."
);
