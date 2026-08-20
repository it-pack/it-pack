<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Port for the placement of one promoted constant: where its declaration lives
 * and which literal occurrences it replaces.
 */
interface ConstantPlacementInterface
{
    public string $className { get; }

    public string $constantName { get; }

    public string $value { get; }

    /** @var list<LiteralInterface> */
    public array $occurrences { get; }
}
