<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\ObservationIdInterface;
use ItPack\RunIt\Domain\Observation\ObservationId;

/**
 * @template-implements StringIdentifierFactoryInterface<ObservationIdInterface>
 */
final class TestObservationIdFactory implements StringIdentifierFactoryInterface
{
    public function build(): ObservationIdInterface
    {
        return ObservationId::fromString('obs-1');
    }
}
