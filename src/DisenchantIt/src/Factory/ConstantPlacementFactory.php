<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Contract\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\ValueObject\ConstantPlacement;
use ItPack\DisenchantIt\ValueObject\ConstantPlacementInterface;

final readonly class ConstantPlacementFactory implements ConstantPlacementFactoryInterface
{
    public function build(
        string $className,
        string $constantName,
        string $value,
        string $type,
        array $occurrences,
    ): ConstantPlacementInterface {
        return new ConstantPlacement($className, $constantName, $value, $type, $occurrences);
    }

    public static function create(): self
    {
        return new self();
    }
}
