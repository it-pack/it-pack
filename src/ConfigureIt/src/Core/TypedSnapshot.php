<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ProvenanceInterface;
use ItPack\ConfigureIt\Exception\TypeException;

/**
 * A snapshot decorator that validates declared scalar types at freeze time.
 *
 * Wraps an immutable {@see ConfigurationSnapshotInterface} and performs a single
 * pass over a declared scalar-type map (`path => 'int'|'float'|'bool'|'string'|
 * 'list'|'map'`). Any leaf that is present but incompatible with its declared
 * type raises {@see TypeException} immediately, so a misconfigured deployment
 * fails before any consumer reads a value. Reads delegate to the inner snapshot,
 * so the surface is identical to a plain snapshot.
 *
 * The resolver mints this decorator when the supplied schema implements
 * {@see \ItPack\ConfigureIt\Contract\DeclaresScalarTypesInterface}; otherwise
 * behaviour is unchanged (ADR-0003).
 *
 * @see docs/architecture/MODEL.md "Typed Snapshot"
 */
final readonly class TypedSnapshot implements ConfigurationSnapshotInterface
{
    /**
     * @param array<string, string> $types dotted path => scalar type name.
     */
    public function __construct(
        private ConfigurationSnapshot $inner,
        private array                 $types,
    ) {
        $this->validate();
    }

    public function get(string $path, mixed $default = null): mixed
    {
        return $this->inner->get($path, $default);
    }

    public function has(string $path): bool
    {
        return $this->inner->has($path);
    }

    public function exists(string $path): bool
    {
        return $this->inner->exists($path);
    }

    public function int(string $path, ?int $default = null): int
    {
        return $this->inner->int($path, $default);
    }

    public function float(string $path, ?float $default = null): float
    {
        return $this->inner->float($path, $default);
    }

    public function bool(string $path, ?bool $default = null): bool
    {
        return $this->inner->bool($path, $default);
    }

    public function string(string $path, ?string $default = null): string
    {
        return $this->inner->string($path, $default);
    }

    /**
     * @param array<int, mixed>|null $default
     */
    public function list(string $path, ?array $default = null): array
    {
        return $this->inner->list($path, $default);
    }

    /**
     * @param array<int, string>|null $default
     */
    public function stringList(string $path, ?array $default = null): array
    {
        return $this->inner->stringList($path, $default);
    }

    public function all(): array
    {
        return $this->inner->all();
    }

    public function provenance(string $path): ?ProvenanceInterface
    {
        return $this->inner->provenance($path);
    }

    /**
     * The wrapped snapshot, exposed so the compiled-artifact cache can
     * serialize its tree + provenance verbatim (M3 — Epic E7.1).
     */
    public function innerSnapshot(): ConfigurationSnapshot
    {
        return $this->inner;
    }

    /**
     * @return array<string, string> dotted path => scalar type name.
     */
    public function scalarTypes(): array
    {
        return $this->types;
    }

    /**
     * Freeze-time validation: every declared, present leaf must conform.
     *
     * @throws TypeException
     */
    private function validate(): void
    {
        foreach ($this->types as $path => $type) {
            if (!$this->inner->has($path)) {
                continue;
            }

            $value = $this->inner->get($path);

            $this->assertType($value, $path, $type);
        }
    }

    /**
     * @throws TypeException
     */
    private function assertType(mixed $value, string $path, string $type): void
    {
        match ($type) {
            'int' => ScalarCaster::toInt($value, $path),
            'float' => ScalarCaster::toFloat($value, $path),
            'bool' => ScalarCaster::toBool($value, $path),
            'string' => ScalarCaster::toString($value, $path),
            'list' => ScalarCaster::toList($value, $path),
            'map' => ScalarCaster::toMap($value, $path),
            default => throw new TypeException(sprintf('Unsupported declared type "%s" for key "%s".', $type, $path)),
        };
    }
}
