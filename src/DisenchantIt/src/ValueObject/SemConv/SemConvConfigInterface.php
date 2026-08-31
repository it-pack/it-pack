<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\SemConv;

use ItPack\DisenchantIt\Contract\SemConv\SemConv;

/**
 * Port for the SemConv generation configuration (interface name, namespace,
 * output path). Concrete carrier: SemConvConfig.
 */
interface SemConvConfigInterface
{
    /** Default name of the generated shared-constants interface. */
    public const string DEFAULT_INTERFACE_NAME = 'SemConv';

    /** Default namespace of the generated shared-constants interface. */
    public const string DEFAULT_NAMESPACE = 'Generated';

    public string $interfaceName { get; }

    public ?string $namespace { get; }

    public string $outputPath { get; }

    public function fullyQualifiedName(): string;
}
