<?php

declare(strict_types=1);

namespace ItPack\HashIt\AlgorithmSource;

use InvalidArgumentException;
use ItPack\HashIt\Contract\AlgorithmSourceInterface;
use ItPack\HashIt\HashAlgorithm;

/**
 * Merges several sources into one catalogue, de-duplicating by algorithm value
 * so a later source cannot silently shadow an earlier one. Resolution and
 * support checks delegate to the first source that knows the identifier.
 */
final readonly class CompositeAlgorithmSource implements AlgorithmSourceInterface
{
    /**
     * @param iterable<AlgorithmSourceInterface> $sources
     */
    public function __construct(
        private iterable $sources,
    ) {
    }

    /**
     * @return list<HashAlgorithm>
     */
    public function algorithms(): array
    {
        $seen = [];
        $ordered = [];

        foreach ($this->sources as $source) {
            foreach ($source->algorithms() as $algorithm) {
                if (!isset($seen[$algorithm->value])) {
                    $seen[$algorithm->value] = true;
                    $ordered[] = $algorithm;
                }
            }
        }

        return $ordered;
    }

    public function supports(string $identifier): bool
    {
        foreach ($this->sources as $source) {
            if ($source->supports($identifier)) {
                return true;
            }
        }

        return false;
    }

    public function resolve(string $identifier): HashAlgorithm
    {
        foreach ($this->sources as $source) {
            if ($source->supports($identifier)) {
                return $source->resolve($identifier);
            }
        }

        throw new InvalidArgumentException('Unknown hash algorithm: ' . $identifier);
    }
}
