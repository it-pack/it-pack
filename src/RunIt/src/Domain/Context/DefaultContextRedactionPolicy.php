<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Context;

use ItPack\RunIt\Contract\Domain\ContextRedactionPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;

/**
 * The default deny-secret context redaction policy.
 *
 * For external publication the policy keeps only what classification permits:
 *
 *  - `Public` entries are kept.
 *  - `Internal` and `Confidential` entries are removed (they are not exportable).
 *  - `Restricted` entries are masked: the key is kept but the value is replaced
 *    with a redaction marker, so consumers learn a restricted value exists
 *    without learning it.
 *  - `Secret` entries are dropped entirely.
 *
 * The receiver context is never mutated.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
final readonly class DefaultContextRedactionPolicy implements ContextRedactionPolicyInterface
{
    public const string REDACTED = '[redacted]';

    /**
     * The canonical default deny-secret redaction policy.
     *
     * Callers that need "the default" depend on this single site rather than
     * constructing the policy ad hoc, so the default choice lives in one place.
     */
    public static function default(): self
    {
        return new self();
    }

    public function redact(ExecutionContextInterface $context): ExecutionContextInterface
    {
        $result = ExecutionContext::empty();

        foreach ($context->entries() as $entry) {
            $classification = $entry->classification();
            $name = $classification->label();

            if ($name === ContextClassification::Secret->value) {
                continue;
            }

            if ($name === ContextClassification::Restricted->value) {
                $result = $result->with(ContextEntry::restricted(
                    $entry->key(),
                    self::REDACTED,
                    $entry->expiresAt(),
                    $entry->source(),
                ));

                continue;
            }

            if ($classification->isExportable()) {
                $result = $result->with($entry);
            }
        }

        return $result;
    }
}
