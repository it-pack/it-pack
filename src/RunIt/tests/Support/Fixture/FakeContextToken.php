<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Opaque context token double.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class FakeContextToken implements ContextTokenInterface
{
    public function id(): int
    {
        return 0;
    }
}
