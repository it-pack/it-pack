<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;

/**
 * Mutable per-Fiber scope slot used by the runtime context storages.
 *
 * The reference storages keep a LIFO token stack and the attached scope per
 * Fiber (or a single process-level fallback outside a Fiber). A real class is
 * used instead of an `object{...}` shape because PHPStan treats shape
 * properties as read-only, which would forbid the LIFO `stack` mutation the
 * protocol requires.
 *
 * It lives in the neutral Domain layer so both the Application (factory) and
 * Adapter (storage) layers may depend on it without violating DepTrac's
 * direction rules.
 *
 * @see https://w3id.org/itpack/runit#Execution
  * @api

  */
final class FiberScopeSlot
{
    /**
     * @var array<int, int>
     */
    public array $stack = [];

    /**
     * @var array<int, ExecutionScopeInterface>
     */
    public array $scopes = [];
}
