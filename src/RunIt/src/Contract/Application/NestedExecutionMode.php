<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

/**
 * The closed set of nested-execution decisions.
 *
 * A nested call to the runner is resolved into exactly one of these modes. They are
 * internal runtime decisions, not domain artifacts, so they live in the application
 * contract namespace and are consumed by the execution runner and nested policies.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
enum NestedExecutionMode: string
{
    case ChildExecution = 'child_execution';
    case ActivityOnly = 'activity_only';
    case DetachedExecution = 'detached_execution';
    case RejectNested = 'reject_nested';
}
