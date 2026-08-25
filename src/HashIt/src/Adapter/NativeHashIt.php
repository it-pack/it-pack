<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use ItPack\HashIt\Hasher;
use ItPack\HashIt\HmacHasherInterface;

/**
 * Composition root: binds the native (PHP hash extension) adapters into the
 * port-based Hasher. The only place in the codebase that knows about concrete
 * adapter classes — keep framework/concrete wiring here, not in the core.
 */
final class NativeHashIt
{
    public static function create(): Hasher
    {
        return new Hasher(new NativeContextFactory(), new NativeDirectoryListing());
    }

    public static function hmacHasher(): HmacHasherInterface
    {
        return new NativeHmacHasher();
    }
}
