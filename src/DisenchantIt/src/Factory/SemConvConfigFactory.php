<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Contract\SemConvConfigFactoryInterface;
use ItPack\DisenchantIt\ValueObject\SemConvConfig;
use ItPack\DisenchantIt\ValueObject\SemConvConfigInterface;

final readonly class SemConvConfigFactory implements SemConvConfigFactoryInterface
{
    public function build(
        ?string $interfaceName = null,
        ?string $namespace = null,
        ?string $outputPath = null,
    ): SemConvConfigInterface {
        return new SemConvConfig(
            interfaceName: $interfaceName ?? SemConvConfig::DEFAULT_INTERFACE_NAME,
            namespace: $namespace ?? SemConvConfig::DEFAULT_NAMESPACE,
            outputPath: $outputPath,
        );
    }

    public static function create(): self
    {
        return new self();
    }
}
