<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Rewrite;

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

    /**
     * Inclusive end line of the literal, when it spans multiple lines (e.g. a
     * heredoc body). `null` (the default) means the edit is confined to a single
     * line at {@see $line}. When set and greater than {@see $line}, the rewriter
     * replaces the whole source span (line … endLine) rather than a substring on
     * one line.
     */
    public ?int $endLine { get; }
}
