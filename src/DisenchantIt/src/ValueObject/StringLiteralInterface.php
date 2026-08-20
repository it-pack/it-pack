<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Port for a detected string literal (a magic-value candidate). Remains a
 * distinct, string-only marker so callers that specifically need string
 * semantics (e.g. naming policies) can type against it; it is now a subtype of
 * the unified LiteralInterface.
 */
interface StringLiteralInterface extends LiteralInterface
{
}
