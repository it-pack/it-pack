<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Result;

/**
 * Severity of a conformance finding.
 *
 * An {@see ViolationSeverity::Error} means the implementation violates the
 * contract and is not conformant. A {@see ViolationSeverity::Warning} records a
 * softer concern (for example a publisher that buffers and could not be observed
 * to emit within the probe window) that does not by itself fail conformance.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
enum ViolationSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
