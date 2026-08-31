<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Adapter\Naming\ConstantNameResolver;
use ItPack\DisenchantIt\Adapter\Naming\NameSlugifier;
use ItPack\DisenchantIt\Adapter\Rewriting\BackedEnumTarget;
use ItPack\DisenchantIt\Adapter\Rewriting\ClassConstantTarget;
use ItPack\DisenchantIt\Adapter\Rewriting\ClassnameToClassConst;
use ItPack\DisenchantIt\Adapter\Rewriting\CompositePromotionTarget;
use ItPack\DisenchantIt\Adapter\Rewriting\FlagSetTarget;
use ItPack\DisenchantIt\Contract\Grouping\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\Contract\Naming\ConstantNameResolverInterface;
use ItPack\DisenchantIt\Contract\Naming\DictionaryNameResolverInterface;
use ItPack\DisenchantIt\Contract\Rewriting\PromotionTargetFactoryInterface;
use ItPack\DisenchantIt\Contract\Rewriting\PromotionTargetInterface;
use ItPack\DisenchantIt\ValueObject\Naming\NamePolicy;

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

    public static function create(
        bool $convertFqcnToClassConst = false,
        ?NamePolicy $namePolicy = null,
        ?DictionaryNameResolverInterface $dictionary = null,
    ): self {
        return new self(
            ConstantPlacementFactory::create(),
            ConstantNameResolver::create($namePolicy, $dictionary),
            $convertFqcnToClassConst,
        );
    }

    public function build(): PromotionTargetInterface
    {
        $targets = [];

        if ($this->convertFqcnToClassConst) {
            $targets[] = new ClassnameToClassConst();
        }

        $slugifier = new NameSlugifier();

        $targets[] = new FlagSetTarget($slugifier);
        $targets[] = new BackedEnumTarget($slugifier);
        $targets[] = new ClassConstantTarget($this->placements, $this->resolver);

        return new CompositePromotionTarget($targets);
    }
}
