<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Reporting;

use ItPack\CommandIt\Adapter\Common\RedactionPolicy;
use ItPack\CommandIt\Contract\Port\ExecutionReporterInterface;
use ItPack\CommandIt\Contract\Port\RedactionPolicyInterface;

/**
 * Composes the safe-by-default reporter pipeline.
 *
 * Any {@see ExecutionReporterInterface} delegate is wrapped in a
 * {@see RedactingExecutionReporter} (secret/oversized/high-cardinality data is
 * redacted before it reaches the sink) and a {@see GuardedExecutionReporter}
 * (recursion guard + separate health path), with a {@see NullExecutionReporter}
 * as the health fallback so a publication failure or self-observation loop never
 * bubbles into the business flow.
 *
 * This lives in the Adapter layer (not the SDK facade) because the SDK must stay
 * adapter-free per the architecture: a consumer composes the safe reporter here
 * and passes it into {@see \ItPack\CommandIt\SDK\CommandIt}. That keeps the
 * dependency direction `SDK → Application → Ports` intact and avoids an
 * `SDK → Adapter` violation.
 *
 * @api
 */
final class SafeReporterFactory
{
    public function __construct(
        private RedactionPolicyInterface $redaction = new RedactionPolicy(),
        private ExecutionReporterInterface $health = new NullExecutionReporter(),
    ) {
    }

    public function wrap(ExecutionReporterInterface $delegate): ExecutionReporterInterface
    {
        return new GuardedExecutionReporter(
            new RedactingExecutionReporter($delegate, $this->redaction),
            $this->health,
        );
    }
}
