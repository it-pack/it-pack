<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Command;

use ItPack\CommandIt\Contract\ValueObject\CommandNameInterface;

/**
 * An immutable command: a validated intent carrying resolved input and an
 * optional payload.
 *
 * A command maps to exactly one {@see \ItPack\CommandIt\Contract\Domain\ExecutionArtifactInterface}
 * when executed, and never retains a mutable execution session.
 *
 * @see https://w3id.org/itpack/runit#ExecutionDefinition Command is a specialization of a RunIt execution definition (an intent that yields exactly one execution).
 *
 * @api
 */
interface CommandInterface
{
    public function name(): CommandNameInterface;

    public function input(): CommandInputInterface;

    /**
     * @return mixed
     */
    public function payload(): mixed;
}
