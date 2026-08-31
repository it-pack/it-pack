<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Rewrite;

use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralKind;

/**
 * Port for the placement of one promoted constant: where its declaration lives
 * and which literal occurrences it replaces.
 */
interface ConstantPlacementInterface
{
    public string $className { get; }

    public string $constantName { get; }

    public string $value { get; }

    /**
     * The declared constant type, as the LiteralKind value (`int`, `float`,
     * `string`). Typed as a string rather than the LiteralKind enum so this
     * port depends only on interfaces (PHPArkitect one-directional rule) — the
     * enum is rehydrated at internal boundaries via `LiteralKind::from()`.
     */
    public string $type { get; }

    /**
     * The PHP type keyword for this placement's declaration (e.g. `int`,
     * `float`, `string`). Centralized through LiteralKind so emitters never
     * compare the raw `type` string against keywords directly.
     */
    /** @var list<LiteralInterface> */
    public array $occurrences { get; }

    public function typeKeyword(): string;
}
