<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use HashContext;

interface ContextFactoryInterface
{
    public function build(): HashContext;
}