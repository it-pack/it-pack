<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Behavior\Domain;

/**
 * Shared accessors for an input spec (argument or option metadata).
 *
 * The using value object declares only the primitives that differ per kind —
 * {@see self::name()} and {@see self::kind()} — while the common projections
 * (required / default / type / description and their derived conveniences) live
 * here once, mirroring {@see CommandNamedBehavior}'s abstract-method harmony.
 *
 * @internal
 */
trait InputSpecBehavior
{
    public function required(): bool
    {
        return $this->required;
    }

    public function defaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function type(): string
    {
        return $this->type;
    }

    // @phpstan-ignore-next-line shipmonk.deadMethod Foundation trait projection; consumed by adapters and handlers.
    public function isRequired(): bool
    {
        return $this->required;
    }

    // @phpstan-ignore-next-line shipmonk.deadMethod Foundation trait projection; consumed by adapters and handlers.
    public function defaultValueOrNull(): mixed
    {
        return $this->defaultValue;
    }

    // @phpstan-ignore-next-line shipmonk.deadMethod Foundation trait projection; consumed by adapters and handlers.
    public function typeOrString(): string
    {
        return $this->type;
    }

    // @phpstan-ignore-next-line shipmonk.deadMethod Foundation trait projection; consumed by coercion error messages.
    public function describe(): string
    {
        return \sprintf('%s "%s" (%s)', $this->kind()->label(), $this->name()->toString(), $this->type);
    }
}
