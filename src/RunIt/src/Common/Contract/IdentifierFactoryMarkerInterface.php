<?php

declare(strict_types=1);

namespace ItPack\RunIt\Common\Contract;

/**
 * Marker for a factory that mints a single kind of object through `build()`.
 *
 * This is the narrow contract behind
 * {@see \ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface}:
 * a factory produces instances of one concrete type. It is intentionally not a
 * universal "factory" vocabulary — the execution, snapshot, and CloudEvent
 * factories expose domain-specific verbs (`start()`, `create()`, `terminal()`)
 * and do not extend this marker, so it never implies a shared creation contract
 * across unrelated factory families.
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
interface IdentifierFactoryMarkerInterface
{
    public function build(): object;
}
