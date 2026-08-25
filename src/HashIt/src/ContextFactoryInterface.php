<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use InvalidArgumentException;

interface ContextFactoryInterface
{
    /**
     * @throws InvalidArgumentException when no context can be built for $algorithm
     */
    public function build(string $algorithm): HashContextInterface;
}
