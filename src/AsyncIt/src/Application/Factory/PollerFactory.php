<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Application\Poller\SelectPoller;
use ItPack\AsyncIt\Contract\PollerPortInterface;

/**
 * Builds the reference {@see SelectPoller}.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 */
final class PollerFactory implements PollerFactoryInterface
{
    public function create(): PollerPortInterface
    {
        return new SelectPoller();
    }
}
