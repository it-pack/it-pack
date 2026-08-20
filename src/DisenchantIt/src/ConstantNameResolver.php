<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\ConstantNameResolverInterface;
use ItPack\DisenchantIt\Contract\NumericNameGeneratorInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\LiteralKind;

/**
 * Default ConstantNameResolver. Replaces the three identical `nameFor()`
 * implementations that used to live in Disenchanter, PerClassConstantStrategy
 * and SameClassConstantStrategy.
 *
 * @see ConstantNameResolverInterface
 */
final readonly class ConstantNameResolver implements ConstantNameResolverInterface
{
    public function __construct(
        private readonly ConstantNameGeneratorInterface $generator,
        private readonly NumericNameGeneratorInterface $numericGenerator,
    ) {
    }

    public function resolve(LiteralInterface $lit): string
    {
        if ($lit->kind === LiteralKind::String->value) {
            return $this->generator->generate($lit->value, $lit->role);
        }

        return $this->numericGenerator->generate($lit, $lit->namingContext) ?? $lit->value;
    }

    public static function create(): self
    {
        return new self(
            ConstantNameGenerator::create(),
            NumericNameGenerator::create(),
        );
    }
}
