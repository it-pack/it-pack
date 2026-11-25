<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Application\Publication;

use Throwable;

/**
 * Guards provenance verification so unverified or forged evidence never passes.
 *
 * Verification compares identity fields, checks export-ID bindings, and validates
 * relationship roles. Any step can throw (mutable evidence, forged artifacts,
 * capacity exhaustion); the guard catches those failures and returns false so
 * callers reject the artifact.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
trait ProvenanceVerificationGuards
{
    /**
     * Runs a verification step that may throw, returning false on failure so
     * the caller rejects unverified evidence.
     *
     * @param callable(): bool $check
     */
    protected function safeVerify(callable $check): bool
    {
        try {
            return $check();
        } catch (Throwable) {
            return false;
        }
    }
}
