<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Reporting;

/**
 * Defines what happens when the in-memory reporter buffer is full.
 *
 * @api
 */
interface OverflowPolicyInterface
{
    /**
     * Returns the stable, lower-case overflow policy name.
     */
    public function label(): string;
}
