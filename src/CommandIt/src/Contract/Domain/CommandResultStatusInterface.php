<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Domain;

/**
 * Contract for the closed outcome status of a command result.
 *
 * @api
 */
interface CommandResultStatusInterface
{
    /**
     * Returns the stable, lower-case status name.
     */
    public function label(): string;
}
