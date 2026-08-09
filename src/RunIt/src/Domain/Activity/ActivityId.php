<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Activity;

use ItPack\RunIt\Behavior\Domain\StringIdentifierBehavior;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;

class ActivityId implements ActivityIdInterface
{
    use StringIdentifierBehavior;

    public function toString(): string
    {
        // TODO: Implement toString() method.
    }

    public function equals(StringIdentifierInterface $other): bool
    {
        // TODO: Implement equals() method.
    }
}
