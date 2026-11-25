<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Behavior\Domain;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;

/**
 * Contract for walking bounded recursively-primitive values.
 *
 * @internal shared validator machinery; not a public contract.
 */
interface BoundedPrimitiveValueWalkerInterface
{
    /**
     * @throws RunItExceptionInterface when the value is not a scalar, null, or a
     *                                 bounded list/map of recursively primitive values
     */
    public function assertSupported(mixed $value): void;
}
