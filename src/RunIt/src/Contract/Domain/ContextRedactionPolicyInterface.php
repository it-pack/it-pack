<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for a policy that redacts a context for external publication.
 *
 * A redaction policy decides, per entry, what survives export. The default
 * deny-secret policy drops secret values and masks restricted values, while
 * non-exportable entries (internal, confidential) are removed.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
interface ContextRedactionPolicyInterface
{
    /**
     * Returns a new context containing only what the policy permits to be
     * exported. The receiver is never mutated.
     */
    public function redact(ExecutionContextInterface $context): ExecutionContextInterface;
}
