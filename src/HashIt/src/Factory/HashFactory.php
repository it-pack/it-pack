<?php

declare(strict_types=1);

namespace ItPack\HashIt\Factory;

use ItPack\HashIt\Hash;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\URNBuilder;

/**
 * The single place in the package that may instantiate {@see Hash} (a
 * value object that is otherwise constructed only by the composition root
 * and the call sites that need to fabricate digests). Hashing internals
 * inject this port and call {@see self::create()} instead of `new Hash(…)`,
 * keeping the value-object creation surface auditable.
 *
 * Layer: Core (per {@see deptrac.yaml} — the `Factory` namespace is not
 * excluded from the Core regex).
 */
final readonly class HashFactory
{
    public function __construct(
        private URNBuilder $urnBuilder = new URNBuilder(),
    ) {
    }

    public function create(
        string $value,
        string|HashAlgorithm $algorithm,
        bool $binary = false,
    ): Hash {
        return new Hash(
            $value,
            $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm,
            $binary,
            $this->urnBuilder
        );
    }
}
