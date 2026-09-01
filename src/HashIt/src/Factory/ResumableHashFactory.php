<?php

declare(strict_types=1);

namespace ItPack\HashIt\Factory;

use ItPack\HashIt\Contract\HashContextInterface;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\ResumableHash;

/**
 * The single place in the package that may instantiate a {@see ResumableHash}
 * envelope. {@see ResumableHasher} injects this port and calls
 * {@see self::create()} instead of `new ResumableHash(…)`, keeping the
 * value-object creation surface auditable.
 *
 * Layer: Core.
 */
final class ResumableHashFactory
{
    public function __construct(private HashFactory $hashFactory)
    {
    }

    public function create(
        HashContextInterface $context,
        string|HashAlgorithm $algorithm,
        bool $binary = false,
    ): ResumableHash {
        return new ResumableHash(
            $context,
            $this->hashFactory,
            $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm,
            $binary,
        );
    }
}
