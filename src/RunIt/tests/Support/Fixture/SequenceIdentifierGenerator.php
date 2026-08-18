<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;

/**
 * Deterministic identifier generator for tests.
 *
 * Returns an incrementing, prefix-tagged value on each call so generated identifiers are
 * unique and predictable. It implements the same port as production generators, so it drops
 * in wherever an {@see IdentifierGeneratorInterface} is required.
 *
 * This is a test double: it is not safe to use in production code.
 */
final class SequenceIdentifierGenerator implements IdentifierGeneratorInterface
{
    private int $counter = 0;

    public function __construct(private readonly string $prefix = 'id')
    {
    }

    public function generate(): string
    {
        $this->counter++;

        return sprintf('%s-%d', $this->prefix, $this->counter);
    }
}
