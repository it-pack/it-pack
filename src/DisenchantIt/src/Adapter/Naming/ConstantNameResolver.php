<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Naming;

use ItPack\DisenchantIt\Adapter\Grouping\PerClassConstantStrategy;
use ItPack\DisenchantIt\Adapter\Grouping\SameClassConstantStrategy;
use ItPack\DisenchantIt\Contract\Naming\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\Naming\ConstantNameResolverInterface;
use ItPack\DisenchantIt\Contract\Naming\DictionaryNameResolverInterface;
use ItPack\DisenchantIt\Contract\Naming\NumericNameGeneratorInterface;
use ItPack\DisenchantIt\Core\Disenchanter;
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
        private readonly ConstantNameGeneratorInterface $generator,
        private readonly NumericNameGeneratorInterface $numericGenerator,
        private readonly ?NamePolicy $policy = null,
        private readonly ?DictionaryNameResolverInterface $dictionary = null,
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

        if ($this->dictionary !== null) {
            $base = $this->dictionary->resolve($lit);
        }

        if ($base === null) {
            $base = $lit->kind === LiteralKind::String->value
                ? $this->generator->generate($lit->value, $lit->role->value)
                : ($this->numericGenerator->generate($lit, $lit->namingContext) ?? $lit->value);
        }

        return $this->policy !== null ? $this->policy->apply($base) : $base;
    }
}
