<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an attribute key.
 *
 * An attribute key is a stable, validated string. It follows the same
 * structural rules as other RunIt string values so keys stay safe in logs,
 * headers, and serialized attributes. Keys are extensible: consumers use
 * namespaced keys (for example `http.status`) without a closed vocabulary.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
interface AttributeKeyInterface extends StringValueInterface
{
}
