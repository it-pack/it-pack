<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Fiber;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Token tying an attached scope to the Fiber (or main stack) that owns it.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class FiberContextToken implements ContextTokenInterface
{
    /**
     * @param object $anchor the Fiber instance, or the main sentinel when outside a Fiber
     */
    public function __construct(
        public object                  $anchor,
        public ExecutionScopeInterface $scope,
    ) {
    }

    public function id(): int
    {
        return spl_object_id($this->anchor);
    }
}
