<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Port for the immutable "should this file / literal be analysed?" configuration.
 *
 * Exists so that {@see \ItPack\DisenchantIt\Contract\ScopeFilterInterface} and
 * {@see \ItPack\DisenchantIt\Contract\CandidateFilterInterface} can depend on an
 * interface (clean layered architecture) rather than the concrete
 * {@see DisenchantScope} value object. Mirrors the LiteralInterface / literal
 * carrier split elsewhere in the ValueObject namespace.
 */
interface DisenchantScopeInterface
{
    /**
     * Default cap (in characters) for heredoc/nowdoc bodies collected as
     * candidate strings. Keeps the opt-in string-coverage scan bounded; a 50 KB
     * prose heredoc is skipped by default.
     */
    public const int DEFAULT_MAX_HEREDOC_BODY_CHARS = 4096;

    /**
     * @return list<string> glob patterns; a file is *allowed* only when it matches one (allow-list).
     */
    public function getIncludePaths(): array;

    /**
     * @return list<string> glob patterns; a matching file is always rejected.
     */
    public function getExcludePaths(): array;

    /**
     * @return list<string> namespace prefixes a literal's class must sit in to be allowed (allow-list).
     */
    public function getIncludeNamespaces(): array;

    /**
     * @return list<string> namespace prefixes a literal's class must NOT sit in (deny-list).
     */
    public function getExcludeNamespaces(): array;

    /**
     * @return list<string> literal scalar values that are always rejected, regardless of file.
     */
    public function getExcludeLiteralValues(): array;

    /**
     * When true, files under the conventional test/fixture/var directories
     * (tests/, fixtures/, var/) are rejected.
     */
    public function isIgnoreTests(): bool;

    /**
     * Maximum accepted length (in characters) of a heredoc/nowdoc body that
     * DisenchantIt will collect as a candidate string. Larger bodies are skipped
     * at detection time (opt-in string coverage, bounded cost). 0 means "no
     * limit"; the default is a conservative cap.
     */
    public function getMaxHeredocBodyChars(): int;

    /**
     * When true, fully-qualified class-name strings (`App\Foo\Bar`) are eligible
     * for the opt-in `ClassnameToClassConst` converter (rewritten to
     * `Bar::class`) instead of being excluded entirely.
     */
    public function isConvertFqcnToClassConst(): bool;

    /**
     * Immutable copy with the FQCN → `::class` converter flag toggled.
     *
     * @return static
     */
    public function withConvertFqcnToClassConst(bool $enabled): self;
}
