<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Context;

use ItPack\RunIt\Contract\Domain\ContextClassificationInterface;
use ItPack\RunIt\Contract\Domain\ContextPropagationPolicyInterface;

/**
 * The default context propagation policy.
 *
 * Delegates the propagation decision to the classification's own behavior:
 * `Public` and `Internal` values propagate by default; `Confidential`,
 * `Restricted`, and `Secret` do not.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
final readonly class DefaultContextPropagationPolicy implements ContextPropagationPolicyInterface
{
    public function isPropagatable(ContextClassificationInterface $classification): bool
    {
        return $classification->isPropagatable();
    }
}
