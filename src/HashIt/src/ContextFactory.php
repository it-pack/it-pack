<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use HashContext;

final readonly class ContextFactory implements ContextFactoryInterface
{
    public function __construct(public string $algo)
    {
    }

    public function build(): HashContext
    {
        return hash_init($this->algo);
    }
}