<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Behavior\Domain;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;

/**
 * Contract for validating keys against bounded key rules.
 *
 * @internal shared validator machinery; not a public contract.
 */
interface BoundedKeyGuardInterface
{
    /**
     * @throws RunItExceptionInterface when the key violates a key rule
     */
    public function assertValidKey(string $key): void;
}
