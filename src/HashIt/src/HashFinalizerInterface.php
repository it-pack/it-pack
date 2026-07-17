<?php

declare(strict_types=1);

namespace ItPack\HashIt\src;

use HashContext;

interface HashFinalizerInterface
{
    public function finalize(HashContext $context): string;
}