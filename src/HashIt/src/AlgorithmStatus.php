<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Cryptographic strength rating for a hashing algorithm.
 *
 * This rates collision/cryptographic strength only. It is orthogonal to
 * HMAC-safety (see #[HmacSafe]): an algorithm can be Broken for collision
 * resistance yet still be perfectly usable as an HMAC keyed-hash function.
 */
enum AlgorithmStatus: string
{
    case Recommended = 'recommended';
    case Legacy = 'legacy';
    case Weak = 'weak';
    case Broken = 'broken';
}
