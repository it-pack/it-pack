<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Vocabulary constants for conformance violation identifiers.
 *
 * Verifiers tag every recorded finding with one of these stable ids so a suite
 * can assert the exact rule that fired. Severity is modelled separately by
 * {@see \ItPack\AsyncIt\Conformance\Result\ViolationSeverity} (ontology
 * individual below); lifecycle-style findings mirror AsyncIt occurrence types.
 *
 * @see https://w3id.org/itpack/asyncit#ViolationSeverity
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-cancellation-observed
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-handle-released
  * @api

  */
interface ViolationVocabulary
{
    public const CONTEXT_BLEED = 'ctx-bleed';

    public const HANDLE_LEAK = 'handle-leak';

    public const FIXTURE_UNSUPPORTED = 'fixture-unsupported';
}
