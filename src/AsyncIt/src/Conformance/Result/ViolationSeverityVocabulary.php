<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Result;

/**
 * Vocabulary constants for conformance violation severities.
 *
 * Each constant is the string value bound to a {@see ViolationSeverity} enum
 * case, providing a named reference for the severity strings used in
 * conformance results.
 *
 * @see https://w3id.org/itpack/asyncit#ViolationSeverity
 */
interface ViolationSeverityVocabulary
{
    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_WARNING = 'warning';
}
