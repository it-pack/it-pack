<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

use ItPack\CommandIt\Contract\Domain\Classification;
use ItPack\CommandIt\Contract\Domain\CommandContextInterface;

/**
 * Immutable command context: classified entries keyed by string.
 *
 * @api
 */
final readonly class CommandContext implements CommandContextInterface
{
    /**
     * @param array<string, array{value: mixed, classification: Classification}> $entries
     */
    public function __construct(
        private array $entries = [],
    ) {
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->entries);
    }

    public function get(string $key): mixed
    {
        return $this->entries[$key]['value'] ?? null;
    }

    public function classification(string $key): ?Classification
    {
        return $this->entries[$key]['classification'] ?? null;
    }

    public function with(string $key, mixed $value, Classification $classification): self
    {
        $entries = $this->entries;
        $entries[$key] = ['value' => $value, 'classification' => $classification];

        return new self($entries);
    }

    public function redacted(): array
    {
        $out = [];

        foreach ($this->entries as $key => $entry) {
            $out[$key] = $entry['classification']->isSecret() ? '***' : $entry['value'];
        }

        return $out;
    }

    public function entries(): array
    {
        return $this->entries;
    }
}
