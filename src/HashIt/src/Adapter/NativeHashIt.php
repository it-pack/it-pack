<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use ItPack\HashIt\AlgorithmSourceInterface;
use ItPack\HashIt\Argon2Interface;
use ItPack\HashIt\Hasher;
use ItPack\HashIt\HasherInterface;
use ItPack\HashIt\HashingObserverInterface;
use ItPack\HashIt\HmacHasherInterface;
use ItPack\HashIt\KeyDerivation\Hkdf;
use ItPack\HashIt\KeyDerivationInterface;
use ItPack\HashIt\KeyedHasher;
use ItPack\HashIt\KeyedHasherInterface;
use ItPack\HashIt\Pbkdf2Interface;

/**
 * Composition root: binds the native (PHP hash extension) adapters into the
 * port-based Hasher. The only place in the codebase that knows about concrete
 * adapter classes — keep framework/concrete wiring here, not in the core.
 */
final readonly class NativeHashIt
{
    public static function create(?HashingObserverInterface $observer = null, ?AlgorithmSourceInterface $source = null): HasherInterface
    {
        return new Hasher(new NativeContextFactory(), new NativeDirectoryListing(), $observer, $source);
    }

    public static function hmacHasher(?AlgorithmSourceInterface $source = null): HmacHasherInterface
    {
        return new NativeHmacHasher($source);
    }

    public static function keyedHasher(?HashingObserverInterface $observer = null, ?AlgorithmSourceInterface $source = null): KeyedHasherInterface
    {
        return new KeyedHasher(new NativeKeyedContextFactory(), $observer, $source);
    }

    /**
     * HKDF key derivation (RFC 5869), built on the keyed hasher so derivations
     * are observable when an observer is supplied.
     */
    public static function kdf(?HashingObserverInterface $observer = null): KeyDerivationInterface
    {
        return new Hkdf(self::keyedHasher($observer));
    }

    /**
     * PBKDF2 key derivation (iteration-hardened, for password stretching).
     */
    public static function pbkdf2(): Pbkdf2Interface
    {
        return new NativePbkdf2();
    }

    /**
     * Argon2id password hashing / verification.
     */
    public static function argon2(): Argon2Interface
    {
        return new NativeArgon2();
    }
}
