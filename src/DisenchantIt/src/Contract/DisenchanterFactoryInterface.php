<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

/**
 * Port for assembling the Disenchanter. Concrete builder lives in the Factory
 * namespace and is the composition root that wires the scanner, filter, grouper
 * and name generator together.
 */
interface DisenchanterFactoryInterface
{
    public function build(): DisenchanterInterface;
}
