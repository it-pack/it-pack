<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceIdInterface;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;

/**
 * @template-implements StringIdentifierFactoryInterface<OccurrenceIdInterface>
 */
final class TestOccurrenceIdFactory implements StringIdentifierFactoryInterface
{
    public function build(): OccurrenceIdInterface
    {
        return OccurrenceId::fromString('occ-1');
    }
}
