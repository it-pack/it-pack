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

    /**
     * The declared constant type, as the LiteralKind value (`int`, `float`,
     * `string`). Typed as a string rather than the LiteralKind enum so this
     * port depends only on interfaces (PHPArkitect one-directional rule) — the
     * enum is rehydrated at internal boundaries via `LiteralKind::from()`.
     */
    public string $type { get; }

    /** @var list<LiteralInterface> */
    public array $occurrences { get; }
}
