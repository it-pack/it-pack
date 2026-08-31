<?php

declare(strict_types=1);

namespace ItPack\HashIt\AlgorithmSource;

use InvalidArgumentException;
use ItPack\HashIt\Contract\AlgorithmSourceInterface;
use ItPack\HashIt\HashAlgorithm;

/**
 * Default source: enumerates every case of the {@see HashAlgorithm} enum.
 * Resolution matches both the enum *value* ("sha256") and the enum *name*
 * ("SHA256"), case-insensitively on the name.
 */
final class NativeAlgorithmSource implements AlgorithmSourceInterface
{
    /**
     * @return list<HashAlgorithm>
     */
    public function algorithms(): array
    {
        return HashAlgorithm::cases();
    }

    public function supports(string $identifier): bool
    {
        return $this->match($identifier) !== null;
    }

    public function resolve(string $identifier): HashAlgorithm
    {
        $match = $this->match($identifier);

        if ($match === null) {
            throw new InvalidArgumentException('Unknown hash algorithm: ' . $identifier);
        }

        return $match;
    }

    private function match(string $identifier): ?HashAlgorithm
    {
        foreach (HashAlgorithm::cases() as $case) {
            if ($case->value === $identifier || strtolower($case->name) === strtolower($identifier)) {
                return $case;
            }
        }

        return null;
    }
}
