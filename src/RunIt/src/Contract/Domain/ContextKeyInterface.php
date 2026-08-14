<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an immutable, validated context key.
 *
 * Context keys follow the same structural rules as other RunIt string values so
 * they stay safe in logs, headers, and serialized context. Keys are
 * extensible: consumers use namespaced keys (for example `tenant.id`) without a
 * closed vocabulary.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
interface ContextKeyInterface extends StringValueInterface
{
}
