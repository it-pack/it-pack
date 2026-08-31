<?php

declare(strict_types=1);

namespace ItPack\HashIt\Contract;

use Stringable;

interface HashInterface extends Stringable
{
    public function algorithm(): string;

    public function length(): int;

    public function binary(): bool;

    public function value(): string;

    public function equals(HashInterface $other): bool;

    /**
     * Deterministic content-address URN for this digest.
     *
     * Format: urn:hashit:<algorithm>:<hex>
     * where <hex> is the hex encoding of the raw digest bytes, independent of
     * the $binary flag — so a binary and a non-binary Hash of the same digest
     * produce an identical URN. This is the only content-addressing sliver
     * allowed (full CAS storage lives in a separate package).
     */
    public function toUrn(): string;
}
