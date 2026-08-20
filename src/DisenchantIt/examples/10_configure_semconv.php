<?php

declare(strict_types=1);

/**
 * Example 10 — configuring the SemConv registry.
 *
 * The registry name, namespace, and output path are configuration, not
 * hardcoded. Build the collector and alias rewriter from one shared
 * SemConvConfig so the emitted `use` import and the registry namespace match.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DisenchantIt\ValueObject\SemConvConfig;

hr('Custom SemConvConfig — rename the shared registry');

$code = <<<'PHP'
<?php

namespace Demo;

class Plan {
    public function tier(): string { return 'pro-plan'; }
    public function tier2(): string { return 'pro-plan'; }
}
PHP;

code('INPUT', $code);

$config = new SemConvConfig(interfaceName: 'AppConstants', namespace: 'App\\Generated');
$result = buildCustom(semconv: $config)->rewriteWithSemConv($code);

code('OUTPUT — $result->code', $result->code);
code('OUTPUT — $result->semconvSource', $result->semconvSource);

note(
    "The class now aliases `App\\Generated\\AppConstants::PRO_PLAN` and the generated registry is "
    . "`interface AppConstants` in namespace `App\\Generated`. Keep the SAME SemConvConfig instance "
    . "for the collector and the alias rewriter or the import and registry will not line up."
);
