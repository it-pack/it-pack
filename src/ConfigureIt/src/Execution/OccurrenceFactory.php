<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Execution;

use ItPack\ConfigureIt\Behavior\CreatableBehavior;
use ItPack\ConfigureIt\Contract\Execution\OccurrenceFactoryInterface;

class OccurrenceFactory implements OccurrenceFactoryInterface
{
    use CreatableBehavior;

    public static function createLLazy(): self
    {
        return new \ReflectionClass(self::class)->newLazyProxy(

        );
    }

    public function build(
        string $type,
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