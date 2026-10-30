<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Discriminated union representing the cooperation point at which a task
 * is suspended.
 *
 * @see https://w3id.org/itpack/asyncit#Awaitable
 * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-readable
 * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-writable
 * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-delay
 * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-channel
 * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-join
 */
enum AwaitPoint
{
    case Readable;

    case Writable;

    case Delay;

    case Channel;

    case Join;
}
