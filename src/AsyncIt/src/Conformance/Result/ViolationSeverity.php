<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Result;

/**
 * Severity of a conformance finding.
 *
 * An {@see ViolationSeverity::Error} means the implementation violates the
 * contract and is not conformant. A {@see ViolationSeverity::Warning} records a
 * softer concern (for example a poller that could not be observed to release a
 * watcher within the probe window) that does not by itself fail conformance.
 *
 * @see https://w3id.org/itpack/asyncit#ViolationSeverity
 * @see https://w3id.org/itpack/runit#Execution
 */
enum ViolationSeverity: string
{
    case Error = ViolationSeverityVocabulary::SEVERITY_ERROR;
    case Warning = ViolationSeverityVocabulary::SEVERITY_WARNING;
}
