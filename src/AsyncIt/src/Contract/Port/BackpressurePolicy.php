<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Policy governing how the scheduler reacts when admission would exceed
 * capacity (e.g. TaskGroup maxConcurrency or channel capacity).
 *
 * @see https://w3id.org/itpack/asyncit#BackpressurePolicy
 * @see https://w3id.org/itpack/asyncit-taxonomy#backpressure-block
 * @see https://w3id.org/itpack/asyncit-taxonomy#backpressure-drop
 * @see https://w3id.org/itpack/asyncit-taxonomy#backpressure-error
 */
enum BackpressurePolicy: string
{
    case Block = 'block';

    case Drop = 'drop';

    case Error = 'error';
}
