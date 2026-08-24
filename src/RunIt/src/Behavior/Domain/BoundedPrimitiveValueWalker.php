<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

use Closure;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

/**
 * The single bounded recursively-primitive value walk shared by the context and
 * attribute validators.
 *
 * Both rule sets accept exactly the same shapes — scalars, null, and lists/maps
 * whose members are themselves scalars or null — bounded by string length,
 * nesting depth, and (optionally) per-array entry count. Only the exception
 * carrying the violation differs between the two public rule sets, so the walk
 * is parameterized by exception factories instead of being duplicated.
 *
 * @internal shared validator machinery; not a public contract.
 */
final readonly class BoundedPrimitiveValueWalker
{
    /**
     * @param Closure(int $length, int $maximum): RunItExceptionInterface $valueTooLong
     * @param Closure(int $depth, int $maximum): RunItExceptionInterface $valueTooDeep
     * @param Closure(): RunItExceptionInterface                          $unsupportedValue
     * @param Closure(int $count, int $maximum): RunItExceptionInterface  $oversizedArray raised when a nested array exceeds the entry bound (rule-set dependent)
     */
    public function __construct(
        private readonly SecurityLimits $limits,
        private readonly Closure $valueTooLong,
        private readonly Closure $valueTooDeep,
        private readonly Closure $unsupportedValue,
        private readonly Closure $oversizedArray,
        private readonly bool $rejectOversizedArrays = false,
    ) {
    }

    /**
     * @throws RunItExceptionInterface when the value is not a scalar, null, or a
     *                                 bounded list/map of scalars
     */
    public function assertSupported(mixed $value): void
    {
        $this->walk($value, 1, $this->limits->maxAttributeDepth);
    }

    /**
     * @throws RunItExceptionInterface when the value is not a scalar, null, or a
     *                                 bounded list/map of scalars
     */
    private function walk(mixed $value, int $depth, int $maxDepth): void
    {
        if (is_scalar($value) || $value === null) {
            $maxLength = $this->limits->maxAttributeValueBytes;

            if (is_string($value) && strlen($value) > $maxLength) {
                throw ($this->valueTooLong)(strlen($value), $maxLength);
            }

            return;
        }

        if (is_array($value)) {
            if ($depth >= $maxDepth) {
                throw ($this->valueTooDeep)($depth, $maxDepth);
            }

            if ($this->rejectOversizedArrays && count($value) > $this->limits->maxAttributeCount) {
                throw ($this->oversizedArray)(count($value), $this->limits->maxAttributeCount);
            }

            foreach ($value as $member) {
                $this->walk($member, $depth + 1, $maxDepth);
            }

            return;
        }

        throw ($this->unsupportedValue)();
    }
}
