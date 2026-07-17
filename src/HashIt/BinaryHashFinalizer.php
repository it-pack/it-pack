<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use HashContext;

final class BinaryHashFinalizer implements HashFinalizerInterface
{
    public function finalize(HashContext $context): string
    {
        return hash_final($context, true);
    }
}