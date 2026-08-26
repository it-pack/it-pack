<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\PollerPortInterface;

/**
 * Creates the poller backend (Io\Poll, SelectPoller, …) selected by capability.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 */
interface PollerFactoryInterface
{
    public function create(): PollerPortInterface;
}
