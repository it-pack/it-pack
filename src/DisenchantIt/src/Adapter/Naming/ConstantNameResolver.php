<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Naming;

use ItPack\DisenchantIt\Contract\Naming\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\Naming\ConstantNameResolverInterface;
use ItPack\DisenchantIt\Contract\Naming\DictionaryNameResolverInterface;
use ItPack\DisenchantIt\Contract\Naming\NumericNameGeneratorInterface;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralKind;
use ItPack\DisenchantIt\ValueObject\Naming\NamePolicy;

/**
 * Default ConstantNameResolver. Replaces the three identical `nameFor()`
 * implementations that used to live in Disenchanter, PerClassConstantStrategy
 * and SameClassConstantStrategy.
 *
 * An optional naming {@see DictionaryNameResolverInterface} overrides the
 * generated name for any literal it knows; an optional {@see NamePolicy}
 * (prefix / suffix / casing) is then applied to whichever name wins.
 *
 * @see ConstantNameResolverInterface
 */
final readonly class ConstantNameResolver implements ConstantNameResolverInterface
{
    public function __construct(
        private ConstantNameGeneratorInterface $generator,
        private NumericNameGeneratorInterface $numericGenerator,
        private ?NamePolicy $policy = null,
        private ?DictionaryNameResolverInterface $dictionary = null,
    ) {
    }

    public static function create(
        ?NamePolicy $policy = null,
        ?DictionaryNameResolverInterface $dictionary = null,
    ): self {
        return new self(
            ConstantNameGenerator::create(),
            NumericNameGenerator::create(),
            $policy,
            $dictionary,
        );
    }

    public function resolve(LiteralInterface $lit): string
    {
        $base = null;

        if ($this->dictionary instanceof DictionaryNameResolverInterface) {
            $base = $this->dictionary->resolve($lit);
        }

        $base ??= $lit->kind === LiteralKind::String->value
            ? $this->generator->generate($lit->value, $lit->role->value)
            : ($this->numericGenerator->generate($lit, $lit->namingContext) ?? $lit->value);

        return $this->policy?->apply($base) ?? $base;
    }
}
