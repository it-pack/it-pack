<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Context;

use ItPack\RunIt\Contract\Domain\ContextCollisionPolicy;
use ItPack\RunIt\Contract\Domain\ContextEntryInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Exception\InvalidContextValueException;

/**
 * An immutable, bounded execution context.
 *
 * The context is a collection of classified entries keyed by a validated
 * string. It is immutable: `with()`, `without()`, and `merge()` return a new
 * context and never mutate the receiver. Lookups fall back to nothing (absent
 * keys are simply missing); `get()` throws, `find()` returns null. The context
 * enforces a maximum entry count so it cannot become an unbounded
 * data-exfiltration path.
 *
 * @api
 *
 * @implements \IteratorAggregate<string, ContextEntryInterface>
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
final readonly class ExecutionContext implements ExecutionContextInterface, \IteratorAggregate, \Countable
{
    public const int MAX_ENTRIES = 64;

    /**
     * @var array<string, ContextEntryInterface>
     */
    private array $entries;

    /**
     * @param array<string, ContextEntryInterface> $entries
     */
    private function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->entries);
    }

    public function get(string $key): ContextEntryInterface
    {
        if (!array_key_exists($key, $this->entries)) {
            throw InvalidContextValueException::missingKey($key);
        }

        return $this->entries[$key];
    }

    public function find(string $key): ?ContextEntryInterface
    {
        return $this->entries[$key] ?? null;
    }

    public function with(ContextEntryInterface $entry): ExecutionContextInterface
    {
        $key = $entry->key()->toString();
        $next = $this->entries;

        if (!array_key_exists($key, $next) && count($next) >= self::MAX_ENTRIES) {
            throw InvalidContextValueException::tooManyEntries(count($next) + 1, self::MAX_ENTRIES);
        }

        $next[$key] = $entry;

        return new self($next);
    }

    public function without(string $key): ExecutionContextInterface
    {
        if (!array_key_exists($key, $this->entries)) {
            return $this;
        }

        $next = $this->entries;
        unset($next[$key]);

        return new self($next);
    }

    /**
     * @return array<string, ContextEntryInterface>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function merge(ExecutionContextInterface $other, ContextCollisionPolicy $policy): ExecutionContextInterface
    {
        $next = $this->entries;

        foreach ($other->entries() as $entry) {
            $key = $entry->key()->toString();

            if (array_key_exists($key, $next)) {
                if ($policy === ContextCollisionPolicy::Throw) {
                    throw InvalidContextValueException::collision($key);
                }

                if ($policy === ContextCollisionPolicy::Replace) {
                    $next[$key] = $entry;
                }

                continue;
            }

            if (count($next) >= self::MAX_ENTRIES) {
                throw InvalidContextValueException::tooManyEntries(count($next) + 1, self::MAX_ENTRIES);
            }

            $next[$key] = $entry;
        }

        return new self($next);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return \Traversable<string, ContextEntryInterface>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->entries);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        $out = [];

        foreach ($this->entries as $key => $entry) {
            $out[$key] = $entry->toArray();
        }

        ksort($out);

        return $out;
    }
}
