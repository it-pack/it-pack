<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

use ItPack\RunIt\Behavior\Domain\ValidatedStringIdentifierBehavior;
use ItPack\RunIt\Contract\Domain\CorrelationIdInterface;

/**
 * An identity shared by related executions.
 *
 * Correlation identifiers are frequently supplied by a remote caller, so a
 * value reaching this class is untrusted and validated exactly like a locally
 * minted identity.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#correlationId
 */
final class CorrelationId implements CorrelationIdInterface
{
    use ValidatedStringIdentifierBehavior;
}
