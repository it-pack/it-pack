<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Contract\SemConv\SemConvConfigFactoryInterface;
use ItPack\DisenchantIt\ValueObject\SemConv\SemConvConfig;
use ItPack\DisenchantIt\ValueObject\SemConv\SemConvConfigInterface;

final readonly class SemConvConfigFactory implements SemConvConfigFactoryInterface
{
    // @phpstan-ignore shipmonk.deadMethod
    public static function create(): self
    {
        return new self();
    }

    // @phpstan-ignore shipmonk.deadMethod
    public function build(
        ?string $interfaceName = null,
        ?string $namespace = null,
    ): SemConvConfigInterface {
        return new SemConvConfig(
            interfaceName: $interfaceName ?? SemConvConfigInterface::DEFAULT_INTERFACE_NAME,
            namespace: $namespace ?? SemConvConfigInterface::DEFAULT_NAMESPACE,
        );
    }
}
