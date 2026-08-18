<?php

declare(strict_types=1);

namespace _Fixture\Stubs;

class ConflictingTraitsClass
{
    use FirstConflictTrait, SecondConflictTrait {
        FirstConflictTrait::shared insteadof SecondConflictTrait;
    }
}
