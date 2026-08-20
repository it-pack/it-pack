<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Domain;

/**
 * Contract for the closed kind of a command input entry (argument or option).
 *
 * @api
 */
interface InputKindInterface
{
    /**
     * Returns the stable, lower-case kind name.
     */
    public function label(): string;
}
