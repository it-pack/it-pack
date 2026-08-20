<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Log;

/**
 * Defines how the execution-aware logger behaves when an incoming log context
 * already carries a RunIt correlation key.
 *
 * @api
 */
enum LoggerCollisionPolicy: string
{
    /**
     * Keep the caller-supplied value and do not overwrite it. The execution
     * correlation is added under a `runit.` namespaced key instead.
     */
    case PreserveCaller = 'preserve_caller';

    /**
     * Overwrite the caller-supplied value with the active execution correlation.
     */
    case Overwrite = 'overwrite';
}
