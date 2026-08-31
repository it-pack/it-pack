<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use HashContext;
use ItPack\HashIt\Contract\ContextFactoryInterface;
use ItPack\HashIt\Contract\HashContextInterface;

final readonly class NativeContextFactory implements ContextFactoryInterface
{
    public function build(string $algorithm): HashContextInterface
    {
        try {
            return new NativeHashContext($algorithm);
        } catch (\ValueError $e) {
            throw new \InvalidArgumentException('Unknown hashing algorithm: ' . $algorithm, previous: $e);
        }
    }

    public function restore(string $checkpoint): HashContextInterface
    {
        $native = @unserialize($checkpoint, ['allowed_classes' => [HashContext::class]]);

        if (!$native instanceof HashContext) {
            throw new \InvalidArgumentException('Checkpoint does not contain a valid HashContext');
        }

        return new NativeHashContext($native);
    }
}
