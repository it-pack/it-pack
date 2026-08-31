<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Contract\Grouping\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\ValueObject\Rewrite\ConstantPlacement;
use ItPack\DisenchantIt\ValueObject\Rewrite\ConstantPlacementInterface;

final readonly class ConstantPlacementFactory implements ConstantPlacementFactoryInterface
{
    public static function create(): self
    {
        return new self();
    }

    public function build(
        string $className,
        string $constantName,
        string $value,
        string $type,
        array $occurrences,
    ): ConstantPlacementInterface {
        return new ConstantPlacement($className, $constantName, $value, $type, $occurrences);
    }
}
