<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Detection;

use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralSubkind;

/**
 * Port for recognising *what a literal is* by its content, independent of the
 * AST role it plays. The default concrete adapter (ContentClassifier) uses
 * cheap string-shape heuristics plus a role-aware numeric check; later phases
 * refine it (e.g. bitmask detection needs parent bitwise-op context).
 *
 * Pure inspection only — it must never execute, autoload, or re-parse the
 * analysed source beyond the already-parsed literal carried on the value
 * object (per the source-text-safety hard rule in AGENTS.md).
 */
interface ContentClassifierInterface
{
    /**
     * @param bool $inBitwiseContext true when the literal sits inside a bitwise
     *        `|`/`&`/`^` operation (passed by the scanner, which holds the AST
     *        node; the Contract stays vendor-free by taking a scalar flag).
     */
    public function classify(LiteralInterface $lit, bool $inBitwiseContext = false): LiteralSubkind;

    /**
     * True when a string value is a fully-qualified class name
     * (`App\Foo\Bar`): at least one namespace separator and every segment
     * identifier-shaped. Such strings are references, not magic values.
     */
    public function isClassNameString(string $value): bool;
}
