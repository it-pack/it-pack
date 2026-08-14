<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for a policy deciding whether a classification may be serialized.
 *
 * Serialization writes context into an immutable snapshot. The default policy
 * delegates to the classification's own serialization behavior.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
interface ContextSerializationPolicyInterface
{
    public function isSerializable(ContextClassificationInterface $classification): bool;
}
