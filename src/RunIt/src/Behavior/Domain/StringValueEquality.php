<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

use ItPack\RunIt\Common\Contract\StringableInterface;

/**
 * The single value-equality rule shared by every string-backed value object.
 *
 * Two values are equal when they are of exactly the same class and render the
 * same string. Centralized so the class-identity guard cannot be dropped from
 * one implementation while the others keep it (the divergence this replaces was
 * a real inconsistency in `ContextSource::equals()`).
 *
 * @internal shared behavior machinery; not a public contract.
 */
final readonly class StringValueEquality
{
    private function __construct()
    {
    }

    /**
     * @param StringableInterface&object $subject
     */
    public static function equals(StringableInterface $subject, object $other): bool
    {
        if ($other::class !== $subject::class || !$other instanceof StringableInterface) {
            return false;
        }

        return $other->toString() === $subject->toString();
    }
}
