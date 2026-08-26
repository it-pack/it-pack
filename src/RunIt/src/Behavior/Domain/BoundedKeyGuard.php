<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

use Closure;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use ItPack\RunIt\Contract\Shared\KeyPattern;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

/**
 * The single validated-key assertion shared by the context and attribute rule
 * sets.
 *
 * Both rule sets apply exactly the same checks — non-empty, bounded, and made
 * of letters, digits, and the separators `.`, `-`, `_` — differing only in the
 * exception family they raise. The checks are therefore parameterized by
 * exception factories instead of being duplicated.
 *
 * @internal shared validator machinery; not a public contract.
 */
final readonly class BoundedKeyGuard
{
    /**
     * @param Closure(): RunItExceptionInterface                        $emptyKey
     * @param Closure(int $length, int $maximum): RunItExceptionInterface $keyTooLong
     * @param Closure(): RunItExceptionInterface                        $unsupportedCharacters
     */
    public function __construct(
        private SecurityLimits $limits,
        private Closure $emptyKey,
        private Closure $keyTooLong,
        private Closure $unsupportedCharacters,
    ) {
    }

    /**
     * @throws RunItExceptionInterface when the key violates a key rule
     */
    public function assertValidKey(string $key): void
    {
        if ($key === '') {
            throw ($this->emptyKey)();
        }

        $maxLength = $this->limits->maxAttributeKeyBytes;
        $length = strlen($key);

        if ($length > $maxLength) {
            throw ($this->keyTooLong)($length, $maxLength);
        }

        if (preg_match(KeyPattern::VALIDATED_KEY, $key) !== 1) {
            throw ($this->unsupportedCharacters)();
        }
    }
}
