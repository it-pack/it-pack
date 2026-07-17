<?php

declare(strict_types=1);

namespace ItPack\HashIt;

interface ContextFactoryInterface
{
    public function build(string $algorithm): HashContextInterface;
}
