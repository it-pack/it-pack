<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Context;

use ItPack\RunIt\Contract\Domain\ContextClassificationInterface;
use ItPack\RunIt\Contract\Domain\ContextSerializationPolicyInterface;

/**
 * The default context serialization policy.
 *
 * Delegates the serialization decision to the classification's own behavior:
 * every tier is serializable except `Secret`, which is never written to a
 * snapshot.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
final readonly class DefaultContextSerializationPolicy implements ContextSerializationPolicyInterface
{
    public function isSerializable(ContextClassificationInterface $classification): bool
    {
        return $classification->isSerializable();
    }
}
