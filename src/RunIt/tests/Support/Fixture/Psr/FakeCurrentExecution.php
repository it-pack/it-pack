<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use ItPack\RunIt\Contract\Application\CurrentExecutionInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Exception\NoActiveExecutionException;

/**
 * Test double for {@see CurrentExecutionInterface} returning a configured scope.
 */
final class FakeCurrentExecution implements CurrentExecutionInterface
{
    public function __construct(private readonly ?ExecutionScopeInterface $scope = null)
    {
    }

    public function get(): ?ExecutionScopeInterface
    {
        return $this->scope;
    }

    public function require(): ExecutionScopeInterface
    {
        if ($this->scope === null) {
            throw NoActiveExecutionException::instance();
        }

        return $this->scope;
    }
}
