<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for artifacts that carry a bounded set of structured attributes.
 *
 * Attributes are key-value metadata. Keys are stable strings; values are
 * limited to scalars, null, and lists/maps of scalar values. Objects and
 * resources are rejected so normalization and serialization stay safe and
 * bounded. Implementations enforce a maximum key length and attribute count.
 *
 * This base contract returns a deterministic, recursively-primitive map. The
 * M1.5 milestone introduces an immutable `AttributeCollection` value object and
 * this contract will be widened accordingly before the stable `1.0.0` release.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
interface AttributedInterface
{
    /**
     * Returns the attributes carried by this artifact.
     *
     * @return array<string, int|float|string|bool|null|array<int|string, mixed>>
     *         a deterministic, bounded, recursively-primitive attribute map
     */
    public function attributes(): array;
}
