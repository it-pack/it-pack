<?php

declare(strict_types=1);

/**
 * Example 7 — SemConv across multiple files (accumulation).
 *
 * DisenchantIt works on ONE source string at a time; you orchestrate the walk
 * over a tree yourself and thread the registry forward via
 * `$existingSemConvSource`. Re-runs MERGE, so previously collected constants
 * are preserved instead of overwritten.
 */

require __DIR__ . '/bootstrap.php';

hr('Multi-file SemConv — one shared registry, accumulated');

$files = [
    'User.php' => <<<'PHP'
<?php

namespace Demo;

class User {
    public function role(): string { return 'admin-user'; }
    public function role2(): string { return 'admin-user'; }
}
PHP,
    'Order.php' => <<<'PHP'
<?php

namespace Demo;

class Order {
    public function state(): string { return 'shipped'; }
    public function state2(): string { return 'shipped'; }
}
PHP,
];

$disenchanter = build();
$semconvSource = null;

foreach ($files as $name => $source) {
    $result = $disenchanter->rewriteWithSemConv(
        code: $source,
        minOccurrences: 2,
        existingSemConvSource: $semconvSource,
    );

    code("OUTPUT — {$name} (class, aliased)", $result->code);

    $semconvSource = $result->semconvSource; // accumulate across files
}

code('OUTPUT — accumulated shared registry (written once, at the end)', $semconvSource);

note(
    "Each file is rewritten independently, but the registry grows as we thread `$semconvSource` "
    . "forward: by the end it holds both ADMIN_USER and SHIPPED. Write the registry to one file "
    . "(e.g. src/Generated/SemConv.php) after the loop."
);
