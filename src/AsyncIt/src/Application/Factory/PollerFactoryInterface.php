<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\PollerPortInterface;

/**
 * Creates the poller backend (Io\Poll, SelectPoller, …) selected by capability
 * and PHP version.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
  * @api

  */
interface PollerFactoryInterface
{
    public function create(CapabilityDescriptorInterface $descriptor): PollerPortInterface;
}
