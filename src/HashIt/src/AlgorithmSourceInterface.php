<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use InvalidArgumentException;

/**
 * Algorithm *discovery* port — a queryable catalogue of the algorithms a given
 * deployment is willing to admit, separate from the actual hashing surface
 * ({@see HasherInterface} accepts arbitrary string identifiers via its context
 * factory). This lets callers enumerate, check and resolve algorithm identifiers
 * without forking the {@see HashAlgorithm} enum, e.g. to plug in custom or
 * KMS-backed identifiers through a custom source.
 */
interface AlgorithmSourceInterface
{
    /**
     * @return list<HashAlgorithm>
     */
    public function algorithms(): array;

    public function supports(string $identifier): bool;

    /**
     * Resolve a string identifier (the enum value, e.g. "sha256", or the enum
     * name, e.g. "SHA256") to its {@see HashAlgorithm} case.
     *
     * @throws InvalidArgumentException when no known algorithm matches
     */
    public function resolve(string $identifier): HashAlgorithm;
}
