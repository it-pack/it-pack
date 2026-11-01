<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;

/**
 * Bundle of adapter-backed primitives assembled for a runtime.
 *
 * Backs {@see RuntimeAssembly} so runtime assemblies remain mockable and
 * interface-driven.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 * @api
 */
interface RuntimeAssemblyInterface
{
    public function poller(): PollerPortInterface;

    public function storage(): ContextStorageInterface;

    public function channel(): TaskChannelInterface;
}
