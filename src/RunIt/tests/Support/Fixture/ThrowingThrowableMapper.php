<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use RuntimeException;

/**
 * Throwable mapper that always throws, for exercising normalization-failure handling.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ThrowingThrowableMapper implements ThrowableMapperInterface
{
    public function describe(\Throwable $throwable): ThrowableDescriptionInterface
    {
        throw new RuntimeException('throwable mapper is broken');
    }
}
