<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Factory;

use Closure;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;

/**
 * Mints identifiers of one concrete type from a pluggable generator.
 *
 * The factory binds an identifier's named constructor to a generation policy:
 *
 * ```php
 * $executionIds = new StringIdentifierFactory(
 *     ExecutionId::fromString(...),
 *     $generator,
 * );
 *
 * $executionId = $executionIds->build();
 * ```
 *
 * Binding a first-class callable keeps the factory free of reflection and of
 * class-string lookups, and lets the identifier type flow to static analysis.
 * The generated value is validated by the identifier itself, so a defective
 * generator fails at the point of creation rather than at export time.
 *
 * @template TIdentifier of StringIdentifierInterface
 *
 * @implements StringIdentifierFactoryInterface<TIdentifier>
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
final readonly class StringIdentifierFactory implements StringIdentifierFactoryInterface
{
    /**
     * @param Closure(string): TIdentifier $constructor named constructor of the identifier type this factory mints
     */
    public function __construct(
        private Closure $constructor,
        private IdentifierGeneratorInterface $generator,
    ) {
    }

    public function build(): StringIdentifierInterface
    {
        return ($this->constructor)($this->generator->generate());
    }
}
