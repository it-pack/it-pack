<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * One literal → reference edit produced by a PromotionTarget. Carries the exact
 * source line the edit applies to, the exact substring the rewriter searches for
 * (the literal's source token / `needle()`), and the replacement text (e.g.
 * `self::CONST` or `Status::New`).
 *
 * Immutable; one instance per occurrence.
 */
final readonly class Replacement implements ReplacementInterface
{
    public function __construct(
        public int $line,
        public string $needle,
        public string $replacement,
        public ?int $endLine = null,
    ) {
    }
}
