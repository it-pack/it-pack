<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for a correlation identifier shared by related executions.
 *
 * A correlation identifier groups executions that belong to the same logical
 * unit of work across process or service boundaries. Unlike the other RunIt
 * identifiers it is deliberately not unique: several executions share one
 * value, and the value is frequently supplied by a remote caller rather than
 * minted locally.
 *
 * Because the value may be attacker-controlled, it is validated like every
 * other identifier and is never used as a metric dimension by default.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#correlationId
 * @see https://w3id.org/itpack/runit/taxonomy#ctx-correlation-id
 */
interface CorrelationIdInterface extends StringIdentifierInterface
{
}
