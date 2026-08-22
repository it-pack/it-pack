<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Adapter\BackedEnumTarget;
use ItPack\DisenchantIt\Adapter\ClassConstantTarget;
use ItPack\DisenchantIt\Adapter\ClassnameToClassConst;
use ItPack\DisenchantIt\Adapter\CompositePromotionTarget;
use ItPack\DisenchantIt\Adapter\ConstantNameResolver;
use ItPack\DisenchantIt\Adapter\FlagSetTarget;
use ItPack\DisenchantIt\Contract\ConstantNameResolverInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\Contract\PromotionTargetFactoryInterface;
use ItPack\DisenchantIt\Contract\PromotionTargetInterface;

/**
 * Composition root for the composite promotion target. Builds the
 * ClassnameToClassConst (opt-in FQCN → `::class`) first when enabled, then
 * FlagSetTarget (bit-flag sets), BackedEnumTarget (closed sets), and finally
 * ClassConstantTarget (the general fallback), wiring them into a
 * CompositePromotionTarget.
 */
final readonly class PromotionTargetFactory implements PromotionTargetFactoryInterface
{
    public function __construct(
        private ConstantPlacementFactoryInterface $placements,
        private ConstantNameResolverInterface $resolver,
        private bool $convertFqcnToClassConst = false,
    ) {
    }

    public static function create(bool $convertFqcnToClassConst = false): self
    {
        return new self(
            ConstantPlacementFactory::create(),
            ConstantNameResolver::create(),
            $convertFqcnToClassConst,
        );
    }

    public function build(): PromotionTargetInterface
    {
        $targets = [];

        if ($this->convertFqcnToClassConst) {
            $targets[] = new ClassnameToClassConst();
        }

        $targets[] = new FlagSetTarget();
        $targets[] = new BackedEnumTarget();
        $targets[] = new ClassConstantTarget($this->placements, $this->resolver);

        return new CompositePromotionTarget($targets);
    }
}
