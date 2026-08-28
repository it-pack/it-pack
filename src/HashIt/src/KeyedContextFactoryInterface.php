<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Builds an incremental HMAC context for one algorithm/key pair.
 *
 * A keyed context is otherwise identical to a plain {@see HashContextInterface}
 * (update/finalize/copy), so the existing immutable {@see HashBuilder} can feed
 * it without change — this port is what unlocks streaming, binary and bulk HMAC
 * from the same primitives the plain-hashing path already uses.
 *
 * Implementations must reject algorithms that are not HMAC-safe
 * (see {@see HashAlgorithm::isHmacSafe()}) and unknown algorithm identifiers.
 */
interface KeyedContextFactoryInterface
{
    public function build(string|HashAlgorithm $algorithm, string $key): HashContextInterface;
}
