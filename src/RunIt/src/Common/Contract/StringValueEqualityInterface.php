<?php

declare(strict_types=1);

namespace ItPack\RunIt\Common\Contract;

/**
 * Equality rule for string-backed value objects.
 *
 * Two values are equal when they are of exactly the same class and render the
 * same string.
 *
 * @see https://w3id.org/itpack/runit#ValueObject
 */
interface StringValueEqualityInterface
{
    /**
     * @param StringableInterface $subject the value to compare
     * @param object $other the candidate to compare against
     */
    public static function equals(StringableInterface $subject, object $other): bool;
}
