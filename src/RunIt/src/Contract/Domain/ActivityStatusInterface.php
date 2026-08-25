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

    /**
     * Returns whether this non-terminal status may legally move to `$to`.
     *
     * The activity matrix mirrors the execution matrix (`created` may move to
     * `running` or `cancelled`; `running` may move to a terminal status).
     * Terminal statuses never transition, so completing an activity to a
     * non-terminal status is rejected by the aggregate.
     */
    public function canTransitionTo(ActivityStatusInterface $to): bool;
}
