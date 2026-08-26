<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Execution;

use ItPack\ConfigureIt\Behavior\CreatableBehavior;
use ItPack\ConfigureIt\Contract\Execution\OccurrenceFactoryInterface;
use ItPack\ConfigureIt\ValueObject\OccurrenceType;
use ReflectionClass;

class OccurrenceFactory implements OccurrenceFactoryInterface
{
    use CreatableBehavior;

    public static function createLLazy(): self
    {
        return new ReflectionClass(self::class)->newLazyProxy(static fn (): self => new self());
    }

    public function build(
        OccurrenceType $type,
        float $at,
        array $payload = [],
    ): Occurrence {
        return new Occurrence(
            $type,
            $at,
            $payload,
        );
    }
}
