<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use HashContext;

final class HashFinalizer implements HashFinalizerInterface
{
    public function __construct(private bool $binary = false)
    {
    }

    public function finalize(HashContext $context): string
    {
        return hash_final($context, $this->binary);
    }
}
