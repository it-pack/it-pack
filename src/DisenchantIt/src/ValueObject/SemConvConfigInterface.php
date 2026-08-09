<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Port for the SemConv generation configuration (interface name, namespace,
 * output path). Concrete carrier: SemConvConfig.
 */
interface SemConvConfigInterface
{
    public string $interfaceName { get; }

    public ?string $namespace { get; }

    public string $outputPath { get; }

    public function fullyQualifiedName(): string;
}
