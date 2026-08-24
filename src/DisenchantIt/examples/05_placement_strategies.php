<?php

declare(strict_types=1);

/**
 * Example 5 — promotion targets: what gets emitted for a candidate?
 *
 * Given the occurrences of one value, the promotion target decides what is
 * emitted:
 *   • ClassConstantTarget — the general default. Emits a `private const` per
 *     distinct class (SameClass when one class, PerClass when several) and
 *     rewrites each usage to `self::CONST`. This is auto-selected.
 *   • BackedEnumTarget    — for a closed set of same-kind literals that all
 *     share one variable/context, emits a backed `enum` and rewrites usages to
 *     `Enum::Case`. Selected automatically by the composite when the closed-set
 *     detector flags the group.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DisenchantIt\Adapter\BackedEnumTarget;
use ItPack\DisenchantIt\Adapter\ClassConstantTarget;
use ItPack\DisenchantIt\Adapter\ConstantNameResolver;
use ItPack\DisenchantIt\Adapter\NameSlugifier;
use ItPack\DisenchantIt\Contract\PromotionTargetInterface;
use ItPack\DisenchantIt\Factory\ConstantPlacementFactory;
use ItPack\DisenchantIt\Factory\PromotionTargetFactory;

$multiClassCode = <<<'PHP'
<?php

namespace Demo;

class Order {
    public function status(): string { return 'pending'; }
}

class Invoice {
    public function status(): string { return 'pending'; }
}
PHP;

code('INPUT (same literal used in TWO classes)', $multiClassCode);

$strategies = [
    'Auto (default)' => null,
    'ClassConstantTarget only' => new ClassConstantTarget(
        ConstantPlacementFactory::create(),
        ConstantNameResolver::create(),
    ),
    'BackedEnumTarget only' => new BackedEnumTarget(new NameSlugifier()),
];

foreach ($strategies as $label => $target) {
    /** @var PromotionTargetInterface|null $target */
    $disenchanter = buildCustom($target);
    $out = $disenchanter->rewrite($multiClassCode);
    code("OUTPUT — target: {$label}", $out);
}

note(
    "Auto and ClassConstantTarget both emit one `private const string PENDING` per class (the literal "
    . "spans two classes). BackedEnumTarget leaves the multi-class case untouched because it is not a "
    . "closed set — it only fires for a small, stable group of same-kind literals in one shared context."
);

$closedSetCode = <<<'PHP'
<?php

namespace Demo;

class StatusMachine {
    public function label(string $status): string {
        if ($status === 'new') { return 'new'; }
        if ($status === 'active') { return 'active'; }
        if ($status === 'closed') { return 'closed'; }
        return $status;
    }
}
PHP;

code('INPUT (closed set of same-kind literals in one variable context)', $closedSetCode);

$enumDisenchanter = buildCustom(PromotionTargetFactory::create()->build());
$enumOut = $enumDisenchanter->rewrite($closedSetCode);
code('OUTPUT — Auto target promotes the closed set to a backed enum', $enumOut);
