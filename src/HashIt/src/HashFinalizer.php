<?php

declare(strict_types=1);

namespace ItPack\HashIt\src;

use HashContext;

class HashFinalizer implements HashFinalizerInterface
{
    public function __construct(bool $binary = false){}

    public function finalize(HashContext $context): string
    {
        return hash_final($context, false);
    }
}