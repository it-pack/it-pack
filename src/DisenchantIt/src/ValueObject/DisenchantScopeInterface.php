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
}
