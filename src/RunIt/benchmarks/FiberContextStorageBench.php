<?php

declare(strict_types=1);

namespace Benchmarks;

use Fiber;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\Instant;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use PhpBench\Attributes\Warmup;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;

/** Measures interleaved Fiber context attach/detach, including Fiber switches. */
#[OutputTimeUnit('microseconds')]
#[Warmup(2)]
#[Iterations(10)]
#[Revs(10000)]
#[AfterMethods('verify')]
final class FiberContextStorageBench
{
    private FiberContextStorage $storage;
    /** @var Fiber<mixed, mixed, mixed, mixed> */
    private Fiber $first;
    /** @var Fiber<mixed, mixed, mixed, mixed> */
    private Fiber $second;
    private bool $lookup = false;

    #[Subject, BeforeMethods('shallow')]
    public function benchShallow(): void
    {
        $this->first->resume();
        $this->second->resume();
    }

    #[Subject, BeforeMethods('nested')]
    public function benchNested(): void
    {
        $this->first->resume();
        $this->second->resume();
    }

    /** Perform one lookup in each interleaved Fiber, including switches. */
    #[Subject, BeforeMethods('lookupShallow')]
    public function benchLookupShallow(): void
    {
        $this->first->resume();
        $this->second->resume();
    }

    /** Perform one lookup in each deeply nested interleaved Fiber. */
    #[Subject, BeforeMethods('lookupNested')]
    public function benchLookupNested(): void
    {
        $this->first->resume();
        $this->second->resume();
    }

    public function lookupShallow(): void
    {
        $this->lookup = true;
        $this->setUp(1);
    }

    public function lookupNested(): void
    {
        $this->lookup = true;
        $this->setUp(63);
    }

    public function shallow(): void
    {
        $this->lookup = false;
        $this->setUp(1);
    }

    public function nested(): void
    {
        $this->lookup = false;
        $this->setUp(63);
    }

    private function setUp(int $depth): void
    {
        $this->storage = new FiberContextStorage();
        $this->first = $this->fiber('bench-fiber-a', $depth);
        $this->second = $this->fiber('bench-fiber-b', $depth);
        $this->first->start();
        $this->second->start();
        if ($this->storage->current() !== null) {
            throw new RuntimeException('Fiber context leaked to main.');
        }
    }

    /** @return Fiber<mixed, mixed, mixed, mixed> */
    private function fiber(string $id, int $depth): Fiber
    {
        $execution = Execution::start(
            ExecutionId::fromString($id),
            ExecutionDefinition::create(ExecutionName::fromString('operation'), ExecutionKind::fromString('task')),
            Instant::of(1),
        );
        $scope = new RunItTestKit()->buildScope($execution, ExecutionContext::empty());

        return new Fiber(function () use ($scope, $depth): void {
            if ($this->storage->current() !== null) {
                throw new RuntimeException('Fiber inherited another context.');
            }
            $parents = [];
            for ($i = 0; $i < $depth; ++$i) {
                $parents[] = $this->storage->attach($scope);
            }
            $last = null;
            while (Fiber::suspend() !== 'verify') {
                if ($this->lookup) {
                    $last = $this->storage->current();
                } else {
                    $token = $this->storage->attach($scope);
                    $this->storage->detach($token);
                }
            }
            if ($this->lookup && $last !== $scope) {
                throw new RuntimeException('Interleaved lookup returned the wrong scope.');
            }
            if ($this->storage->current() !== $scope) {
                throw new RuntimeException('Fiber context was not restored.');
            }
            while ($parents !== []) {
                $this->storage->detach(array_pop($parents));
            }
            if ($this->storage->current() !== null) {
                throw new RuntimeException('Fiber context was not empty after cleanup.');
            }
        });
    }

    public function verify(): void
    {
        $this->first->resume('verify');
        $this->second->resume('verify');
        if (!$this->first->isTerminated() || !$this->second->isTerminated() || $this->storage->current() !== null) {
            throw new RuntimeException('Fiber cleanup failed.');
        }
    }
}
