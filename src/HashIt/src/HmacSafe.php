<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Marks a HashAlgorithm case as accepted by hash_hmac() / hash_hmac_algos().
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
final readonly class HmacSafe
{
}
