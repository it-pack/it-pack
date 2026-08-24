<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * The default RunIt attribute rules.
 *
 * An attribute key is a validated string: non-empty, bounded, and made of letters,
 * digits, and the separators `.`, `-`, `_`. An attribute value is bounded and
 * recursively primitive: a scalar, null, or a list/map whose members are themselves
 * scalars or null. Limits can be tightened through a {@see \ItPack\RunIt\Domain\Shared\SecurityLimits}
 * instance; when none is supplied the conservative built-in defaults apply.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
interface AttributeValidatorInterface
{
    public function assertValidKey(string $key): void;

    /**
     * @param array<string, mixed> $attributes
     */
    public function assertValidAttributes(array $attributes): void;
}
