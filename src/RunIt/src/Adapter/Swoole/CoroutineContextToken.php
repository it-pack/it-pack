<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Swoole;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Token tying an attached scope to the coroutine id that owns it.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class CoroutineContextToken implements ContextTokenInterface
{
    public function __construct(
        public readonly int $coroutineId,
        public readonly ExecutionScopeInterface $scope,
    ) {
    }
}
