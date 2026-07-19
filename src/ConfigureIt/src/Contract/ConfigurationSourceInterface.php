<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * Discovers and enumerates configuration resources.
 *
 * Responsibilities: discovery, ordering, resource enumeration. A source never
 * parses data and never reads content — it only yields resources.
 *
 * @see docs/architecture/MODEL.md "Configuration Source"
 *
 * @template-covariant T of ConfigurationResourceInterface
 */
interface ConfigurationSourceInterface
{
    /**
     * Discover the resources this source is responsible for.
     *
     * @return iterable<ConfigurationResourceInterface>
     */
    public function discover(): iterable;
}
