<?php

declare(strict_types=1);

namespace ItPack\HashIt\src;

use HashContext;

interface ContextFactoryInterface
{
    public function build(): HashContext;
}