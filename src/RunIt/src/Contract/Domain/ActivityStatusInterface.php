<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the lifecycle status of an activity.
 *
 * Activities mirror execution statuses. Terminal statuses (`succeeded`, `failed`,
 * `cancelled`, `timed_out`) cannot be left once entered.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
interface ActivityStatusInterface
{
    public function label(): string;

    public function isTerminal(): bool;
}
