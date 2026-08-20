<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\ValueObject;

/**
 * A validated, immutable string carried by CommandIt domain artifacts.
 *
 * The value is validated exactly once, on construction, and stored verbatim, so
 * {@see self::toString()} round-trips without trimming, lowercasing, or
 * transcoding. Different concrete subclasses keep distinct type identity while
 * sharing this contract.
 *
 * @api
 */
interface ValidatedStringInterface
{
    public function toString(): string;

    public function equals(ValidatedStringInterface $other): bool;
}
