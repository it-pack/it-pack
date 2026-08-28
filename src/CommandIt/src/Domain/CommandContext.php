<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

use ItPack\CommandIt\Contract\Domain\Classification;
use ItPack\CommandIt\Contract\Domain\CommandContextInterface;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;

/**
 * Immutable command context: classified entries keyed by string.
 *
 * @api
 */
final readonly class CommandContext implements CommandContextInterface
{
    /**
     * @var array<string, array{value: mixed, classification: Classification}>
     */
    private array $entries;

    /**
     * @param array<string, mixed> $entries
     */
    public function __construct(array $entries = [])
    {
        foreach ($entries as $key => $entry) {
            if (!\is_array($entry)
                || !\array_key_exists('value', $entry)
                || !($entry['classification'] ?? null) instanceof Classification
            ) {
                throw InvalidCommandException::malformed(
                    \sprintf('Invalid context entry for key "%s".', $key),
                );
            }
        }

        $this->entries = $entries;
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
            $out[$key] = $entry['classification']->isSecret() ? CommandContextInterface::REDACTED : $entry['value'];
        }

        return $out;
    }

    /**
     * Raw entries, including any {@see Classification::Secret} values.
     *
     * Security: the returned arrays expose secret values in plaintext. This view is
     * for internal/serialization use only. General consumers must use
     * {@see self::redacted()} or {@see self::redactedEntries()} instead.
     *
     * @return array<string, array{value: mixed, classification: Classification}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Export-safe view of the entries: secret values are replaced with
     * {@see CommandContextInterface::REDACTED}, mirroring {@see self::redacted()}.
     *
     * Unlike {@see self::entries()}, this accessor never returns a raw secret value,
     * so it is the correct choice for any general consumer.
     *
     * @return array<string, mixed>
     */
    public function redactedEntries(): array
    {
        return $this->redacted();
    }
}
