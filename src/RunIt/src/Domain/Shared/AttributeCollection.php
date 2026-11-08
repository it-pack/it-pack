<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

use ArrayIterator;
use IteratorAggregate;
use ItPack\RunIt\Behavior\Domain\AttributeValidator;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Exception\InvalidAttributeException;
use Traversable;

/**
 * An immutable, bounded collection of structured attributes.
 *
 * Every attribute is validated on construction. Keys must be valid attribute
 * keys; values must be scalars, null, or bounded lists/maps containing recursively
 * primitive values. Objects and resources are rejected. The collection enforces
 * a maximum key length and attribute count.
 *
 * The collection is immutable: `with()` and `without()` return a new collection
 * and never mutate the receiver. `toArray()` returns a deterministic,
 * recursively-primitive map (keys sorted) suitable for serialization and for
 * direct use as an artifact's attributes.
 *
 * @api
 *
 * @implements IteratorAggregate<string, int|float|string|bool|null|array<int|string, mixed>>
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final readonly class AttributeCollection implements AttributeCollectionInterface, IteratorAggregate
{
    /** @var array<string, int|float|string|bool|null|array<int|string, mixed>> */
    private array $attributes;

    /**
     * @param array<string, mixed> $attributes
     */
    private function __construct(
        array $attributes,
        private SecurityLimits $limits = new SecurityLimits(),
    ) {
        $this->validateAttributes($attributes);
        $this->attributes = $attributes;
    }

    /**
     * Creates a collection from a set of attributes, validating each one.
     *
     * @param array<string, int|float|string|bool|null|array<int|string, mixed>> $attributes
     * @param SecurityLimits|null $limits optional tighter bounds; defaults to the conservative built-in limits
     *
     * @throws InvalidAttributeException when a key or value violates a rule, or
     *                                    the attribute count is exceeded
     */
    public static function fromArray(array $attributes, ?SecurityLimits $limits = null): self
    {
        return new self($attributes, $limits ?? new SecurityLimits());
    }

    /**
     * Returns the empty collection.
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return int|float|string|bool|null|array<int|string, mixed>
     */
    public function get(string $key): int|float|string|bool|null|array
    {
        return $this->attributes[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function isEmpty(): bool
    {
        return $this->attributes === [];
    }

    public function count(): int
    {
        return count($this->attributes);
    }

    public function with(string $key, mixed $value): AttributeCollectionInterface
    {
        $next = $this->attributes;
        $next[$key] = $value;

        return new self($next, $this->limits);
    }

    public function without(string $key): AttributeCollectionInterface
    {
        if (!array_key_exists($key, $this->attributes)) {
            return $this;
        }

        $next = $this->attributes;
        unset($next[$key]);

        return new self($next, $this->limits);
    }

    /**
     * @return Traversable<string, int|float|string|bool|null|array<int|string, mixed>>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * @return array<string, int|float|string|bool|null|array<int|string, mixed>>
     */
    public function toArray(): array
    {
        $sorted = $this->attributes;
        ksort($sorted);

        return $sorted;
    }

    /**
     * Validates the attribute map before it is stored.
     *
     * @param array<string, mixed> $attributes
     *
     * @phpstan-assert array<string, int|float|string|bool|null|array<int|string, mixed>> $attributes
     */
    private function validateAttributes(array $attributes): void
    {
        new AttributeValidator($this->limits)->assertValidAttributes($attributes);
    }
}
