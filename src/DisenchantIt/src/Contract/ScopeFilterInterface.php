<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\LiteralInterface;

/**
 * Port deciding whether a single detected literal — in the context of the file
 * it was found in — should be promoted, given a {@see DisenchantScopeInterface}.
 *
 * The decision is pure string/glob logic (fnmatch on the relative path and
 * equality on the literal value); it never executes the analysed file, so
 * opting out stays within the source-text-in / data-structure-out hard rule.
 *
 * NOTE on the path type: the codebase has no dedicated path value object — the
 * relative path is passed as a plain string (the same boundary representation
 * the SDK consumer already holds). A future dedicated `PathInterface` can be
 * introduced without changing this contract's intent.
 *
 * NOTE on namespaces: `DisenchantScopeInterface` carries `includeNamespaces` /
 * `excludeNamespaces`, but the literal carriers currently expose only a short
 * `className` (no namespace). Namespace filtering is therefore accepted as
 * configuration yet intentionally NOT enforced by this adapter until a
 * namespace source is threaded through detection (deferred — see roadmap).
 */
interface ScopeFilterInterface
{
    /**
     * @param string $filePath relative path of the file the literal was found in
     */
    public function isAllowed(LiteralInterface $lit, string $filePath): bool;
}
