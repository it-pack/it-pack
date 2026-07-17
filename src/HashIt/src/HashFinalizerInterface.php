<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use HashContext;

interface HashFinalizerInterface
{
    public function finalize(HashContext $context): string;
}