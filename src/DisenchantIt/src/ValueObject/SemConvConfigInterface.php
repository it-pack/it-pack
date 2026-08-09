<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Port for the SemConv generation configuration (interface name, namespace,
 * output path). Concrete carrier: SemConvConfig.
 */
interface SemConvConfigInterface
{
    public function fullyQualifiedName(): string;
}
