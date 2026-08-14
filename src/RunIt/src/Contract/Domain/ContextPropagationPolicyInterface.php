<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for a policy deciding whether a classification may be propagated.
 *
 * Propagation moves context to child executions and correlated work. The
 * default policy delegates to the classification's own propagation behavior.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
interface ContextPropagationPolicyInterface
{
    public function isPropagatable(ContextClassificationInterface $classification): bool;
}
