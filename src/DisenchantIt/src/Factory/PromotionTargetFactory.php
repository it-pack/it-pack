<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Adapter\BackedEnumTarget;
use ItPack\DisenchantIt\Adapter\ClassConstantTarget;
use ItPack\DisenchantIt\Adapter\CompositePromotionTarget;
use ItPack\DisenchantIt\Adapter\ConstantNameResolver;
use ItPack\DisenchantIt\Adapter\FlagSetTarget;
use ItPack\DisenchantIt\Contract\ConstantNameResolverInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\Contract\PromotionTargetFactoryInterface;
use ItPack\DisenchantIt\Contract\PromotionTargetInterface;

/**
 * Composition root for the composite promotion target. Builds the
 * FlagSetTarget (bit-flag sets) first, then BackedEnumTarget (closed sets), then
 * ClassConstantTarget (the general fallback), and wires them into a
 * CompositePromotionTarget.
 */
final readonly class PromotionTargetFactory implements PromotionTargetFactoryInterface
{
    public function __construct(
        private ConstantPlacementFactoryInterface $placements,
        private ConstantNameResolverInterface $resolver,
    ) {
    }

    public static function create(): self
    {
        return new self(
            ConstantPlacementFactory::create(),
            ConstantNameResolver::create(),
        );
    }

    public function build(): PromotionTargetInterface
    {
        return new CompositePromotionTarget([
            new FlagSetTarget(),
            new BackedEnumTarget(),
            new ClassConstantTarget($this->placements, $this->resolver),
        ]);
    }
}
