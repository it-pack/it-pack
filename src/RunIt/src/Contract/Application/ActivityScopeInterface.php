<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\ActivityInterface;

/**
 * The ambient view of a running activity.
 *
 * An activity scope is an {@see ExecutionScopeInterface} focused on one activity.
 * Artifacts recorded through it are bound to the activity automatically, while
 * execution-level access and other recorders are delegated to the parent
 * execution scope.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
interface ActivityScopeInterface extends ExecutionScopeInterface
{
    public function activity(): ActivityInterface;

    public function parentScope(): ExecutionScopeInterface;
}
