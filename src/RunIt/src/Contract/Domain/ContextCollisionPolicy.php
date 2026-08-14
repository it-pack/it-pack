<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Strategy for resolving key collisions when two contexts are merged.
 *
 * - `Replace` — the incoming context wins for any shared key.
 * - `Keep` — the receiving context wins for any shared key.
 * - `Throw` — a shared key is treated as a conflict and raises an exception.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
enum ContextCollisionPolicy: string
{
    case Replace = 'replace';
    case Keep = 'keep';
    case Throw = 'throw';
}
