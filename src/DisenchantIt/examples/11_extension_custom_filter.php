<?php

declare(strict_types=1);

/**
 * Example 11 — extension point: inject your own CandidateFilter (a port).
 *
 * DisenchantIt is built on ports & adapters. Every collaborator is supplied by
 * a factory, so you can swap one piece without touching the others. Here we
 * implement CandidateFilterInterface as a thin wrapper around the default
 * filter that additionally drops any literal whose value starts with `tmp_`
 * (temporary keys we never want promoted).
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DisenchantIt\Contract\CandidateFilterInterface;
use ItPack\DisenchantIt\Factory\DetectorFactory;
use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

/**
 * A custom filter: keep everything the DEFAULT filter keeps, then remove any
 * value that looks like a temporary key.
 */
final readonly class TemporaryKeyFilter implements CandidateFilterInterface
{
    public function __construct(private CandidateFilterInterface $inner)
    {
    }

    public function filterCandidates(array $literals): array
    {
        $kept = $this->inner->filterCandidates($literals);

        return array_values(array_filter(
            $kept,
            static fn (StringLiteralInterface $literal): bool => !str_starts_with($literal->value, 'tmp_'),
        ));
    }
}

hr('Custom port — drop temporary (tmp_) keys from candidacy');

$code = <<<'PHP'
<?php

namespace Demo;

class Cache {
    public function a(): string { return 'tmp_cache_key'; }
    public function b(): string { return 'tmp_cache_key'; }
    public function c(): string { return 'real-key'; }
    public function d(): string { return 'real-key'; }
}
PHP;

code('INPUT', $code);

$defaultFilter = DetectorFactory::create()->buildFilter();
$disenchanter = buildCustom(filter: new TemporaryKeyFilter($defaultFilter));

$rewritten = $disenchanter->rewrite($code);

code('OUTPUT (tmp_ keys excluded, real-key promoted)', $rewritten);

note(
    "Because the custom filter extends the DEFAULT filter (rather than replacing it), we keep all "
    . "the built-in noise exclusion for free and only add our one rule. This is the general pattern "
    . "for swapping any Contract port."
);
