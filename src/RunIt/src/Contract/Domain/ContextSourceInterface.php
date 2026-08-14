<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an optional context entry source identifier.
 *
 * A source records where a context value originated (for example a header
 * name, a configuration key, or a process name). It is a validated string value
 * and never carries the value itself.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
interface ContextSourceInterface extends StringProviderInterface
{
}
