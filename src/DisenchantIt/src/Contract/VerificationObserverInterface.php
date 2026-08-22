<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

/**
 * Port invoked by Core after a successful rewrite, so an out-of-process
 * verification (e.g. `php -l` lint) can observe the rewritten source without
 * Core ever executing or autoloading the analysed file. Implemented by
 * {@see \ItPack\DisenchantIt\Adapter\PhpLintObserver}. Optional — Core calls it
 * only when one is wired in.
 */
interface VerificationObserverInterface
{
    public function afterRewrite(string $rewrittenSource): void;
}
