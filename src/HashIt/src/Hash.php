<?php

declare(strict_types=1);

namespace ItPack\HashIt;

final readonly class Hash implements Contract\HashInterface
{
    public function __construct(
        private string $value,
        private string $algorithm,
        private bool $binary = false,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function algorithm(): string
    {
        return $this->algorithm;
    }

    public function binary(): bool
    {
        return $this->binary;
    }

    public function length(): int
    {
        return strlen($this->value);
    }

    public static function create(string $value, string $algorithm, bool $binary = false): self
    {
        return new self($value, $algorithm, $binary);
    }

    public function equals(Contract\HashInterface $other): bool
    {
        if ($this->algorithm !== $other->algorithm() || $this->binary !== $other->binary()) {
            return false;
        }

        return hash_equals($this->value, $other->value());
    }

    public function toUrn(): string
    {
        $hex = $this->binary ? bin2hex($this->value) : $this->value;

        return sprintf('urn:hashit:%s:%s', $this->algorithm, $hex);
    }
}
