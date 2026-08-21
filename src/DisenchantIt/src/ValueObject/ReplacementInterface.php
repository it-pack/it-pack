<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Port for one literal → reference edit: the exact source line, the exact
 * substring the rewriter searches for (the literal's source token / `needle()`),
 * and the replacement text (e.g. `self::CONST` or `Status::New`).
 */
interface ReplacementInterface
{
    public int $line { get; }

    public string $needle { get; }

    public string $replacement { get; }
}
