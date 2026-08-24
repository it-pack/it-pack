<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Context;

use ItPack\RunIt\Contract\Domain\ContextClassificationInterface;
use ItPack\RunIt\Contract\Domain\ContextEntryInterface;
use ItPack\RunIt\Contract\Domain\ContextKeyInterface;
use ItPack\RunIt\Contract\Domain\ContextSourceInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;

/**
 * An immutable, classified context entry.
 *
 * An entry binds a validated key to a bounded value, a classification, and
 * optional metadata (an expiry instant and a source identifier). Entries are
 * immutable; queries return the captured data and `toArray()` projects a
 * deterministic, recursively-primitive map.
 *
 * Named constructors set the classification explicitly. The value is validated
 * on construction and rejected (with a typed exception) when it is not a scalar,
 * null, or scalar-only list/map, or when it exceeds a size bound.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
final readonly class ContextEntry implements ContextEntryInterface
{
    /**
     * @param int|float|string|bool|null|array<int|string, int|float|string|bool|null> $value
     */
    private function __construct(
        private ContextKeyInterface $key,
        /** @var int|float|string|bool|null|array<int|string, int|float|string|bool|null> */
        private int|float|string|bool|null|array $value,
        private ContextClassificationInterface $classification,
        private ?InstantInterface $expiresAt,
        private ?ContextSourceInterface $source,
    ) {
        new ContextValueValidator()->assertSupportedValue($value);
    }

    /**
     * @param int|float|string|bool|null|array<int|string, int|float|string|bool|null> $value
     */
    public static function public(
        ContextKeyInterface $key,
        int|float|string|bool|null|array $value,
        ?InstantInterface $expiresAt = null,
        ?ContextSourceInterface $source = null,
    ): self {
        return new self($key, $value, ContextClassification::Public, $expiresAt, $source);
    }

    /**
     * @param int|float|string|bool|null|array<int|string, int|float|string|bool|null> $value
     */
    public static function internal(
        ContextKeyInterface $key,
        int|float|string|bool|null|array $value,
        ?InstantInterface $expiresAt = null,
        ?ContextSourceInterface $source = null,
    ): self {
        return new self($key, $value, ContextClassification::Internal, $expiresAt, $source);
    }

    /**
     * @param int|float|string|bool|null|array<int|string, int|float|string|bool|null> $value
     */
    public static function confidential(
        ContextKeyInterface $key,
        int|float|string|bool|null|array $value,
        ?InstantInterface $expiresAt = null,
        ?ContextSourceInterface $source = null,
    ): self {
        return new self($key, $value, ContextClassification::Confidential, $expiresAt, $source);
    }

    /**
     * @param int|float|string|bool|null|array<int|string, int|float|string|bool|null> $value
     */
    public static function restricted(
        ContextKeyInterface $key,
        int|float|string|bool|null|array $value,
        ?InstantInterface $expiresAt = null,
        ?ContextSourceInterface $source = null,
    ): self {
        return new self($key, $value, ContextClassification::Restricted, $expiresAt, $source);
    }

    /**
     * @param int|float|string|bool|null|array<int|string, int|float|string|bool|null> $value
     */
    public static function secret(
        ContextKeyInterface $key,
        int|float|string|bool|null|array $value,
        ?InstantInterface $expiresAt = null,
        ?ContextSourceInterface $source = null,
    ): self {
        return new self($key, $value, ContextClassification::Secret, $expiresAt, $source);
    }

    public function key(): ContextKeyInterface
    {
        return $this->key;
    }

    public function value(): int|float|string|bool|null|array
    {
        return $this->value;
    }

    public function classification(): ContextClassificationInterface
    {
        return $this->classification;
    }

    public function expiresAt(): ?InstantInterface
    {
        return $this->expiresAt;
    }

    public function source(): ?ContextSourceInterface
    {
        return $this->source;
    }

    public function isExpired(InstantInterface $now): bool
    {
        return $this->expiresAt !== null
            && $this->expiresAt->epochSeconds() <= $now->epochSeconds();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ArtifactField::KEY => $this->key->toString(),
            ArtifactField::VALUE => $this->value,
            ArtifactField::CLASSIFICATION => $this->classification->label(),
            ArtifactField::EXPIRES_AT => $this->expiresAt?->epochSeconds(),
            ArtifactField::SOURCE => $this->source?->toString(),
        ];
    }
}
