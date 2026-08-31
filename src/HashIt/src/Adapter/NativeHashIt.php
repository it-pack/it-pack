<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use ItPack\HashIt\Contract\AlgorithmSourceInterface;
use ItPack\HashIt\Contract\Argon2Interface;
use ItPack\HashIt\Contract\HasherInterface;
use ItPack\HashIt\Contract\HashingObserverInterface;
use ItPack\HashIt\Contract\HmacHasherInterface;
use ItPack\HashIt\Contract\KeyDerivationInterface;
use ItPack\HashIt\Contract\KeyedHasherInterface;
use ItPack\HashIt\Contract\Pbkdf2Interface;
use ItPack\HashIt\Contract\ResumableHashingInterface;
use ItPack\HashIt\Contract\TreeHashingInterface;
use ItPack\HashIt\Hasher;
use ItPack\HashIt\KeyDerivation\Hkdf;
use ItPack\HashIt\KeyedHasher;
use ItPack\HashIt\ResumableHasher;
use ItPack\HashIt\TreeHasher;

/**
 * Composition root: binds the native (PHP hash extension) adapters into the
 * port-based Hasher. The only place in the codebase that knows about concrete
 * adapter classes — keep framework/concrete wiring here, not in the core.
 */
final readonly class NativeHashIt
{
    public static function create(
        ?HashingObserverInterface $observer = null,
        ?AlgorithmSourceInterface $source = null,
    ): HasherInterface {
        return new Hasher(new NativeContextFactory(), new NativeDirectoryListing(), $observer, $source);
    }

    public static function hmacHasher(?AlgorithmSourceInterface $source = null): HmacHasherInterface
    {
        return new NativeHmacHasher($source);
    }

    public static function keyedHasher(
        ?HashingObserverInterface $observer = null,
        ?AlgorithmSourceInterface $source = null,
    ): KeyedHasherInterface {
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

    /**
     * Tree (chunked) hashing — splits a large input into chunks, hashes each,
     * and combines the chunk digests into a single root digest distinct from
     * hash(). Scheme and framing: docs/ADR-0001-tree-hash-combiner.md.
     */
    public static function treeHasher(): TreeHashingInterface
    {
        return new TreeHasher(new NativeContextFactory());
    }

    /**
     * Resumable (pausable) hashing — checkpoint an in-progress stream hash and
     * resume it later (even in another process). Plain algorithms only; HMAC
     * contexts cannot be serialized (docs/ADR-0002).
     */
    public static function resumable(): ResumableHashingInterface
    {
        return new ResumableHasher(new NativeContextFactory());
    }
}
