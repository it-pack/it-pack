<?php

declare(strict_types=1);

namespace ItPack\HashIt\Contract;

use InvalidArgumentException;

interface ContextFactoryInterface
{
    /**
     * @throws InvalidArgumentException when no context can be built for $algorithm
     */
    public function build(string $algorithm): HashContextInterface;

    /**
     * Rebuild a context from a checkpoint produced by
     * {@see HashContextInterface::checkpoint()}.
     *
     * @throws InvalidArgumentException when $checkpoint is not a valid checkpoint
     */
    public function restore(string $checkpoint): HashContextInterface;
}
