<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use ItPack\HashIt\ContextFactoryInterface;
use ItPack\HashIt\HashContextInterface;

final class NativeContextFactory implements ContextFactoryInterface
{
    public function build(string $algorithm): HashContextInterface
    {
        try {
            return new NativeHashContext($algorithm);
        } catch (\ValueError $e) {
            throw new \InvalidArgumentException('Unknown hashing algorithm: ' . $algorithm, previous: $e);
        }
    }
}
