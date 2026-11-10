<?php

declare(strict_types=1);

namespace Benchmarks;

use ItPack\RunIt\Adapter\Fiber\FiberRuntimeDetector;
use ItPack\RunIt\Application\Propagation\CompositeContextStorage;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
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

/** Measures synchronous attach/detach with and without composite delegation. */
#[OutputTimeUnit('microseconds')]
#[Warmup(2)]
#[Iterations(10)]
#[Revs(10000)]
#[AfterMethods('verify')]
final class ContextStorageBench
{
    private ContextStorageInterface $storage;
    private ExecutionScopeInterface $scope;
    /** @var list<ContextTokenInterface> */
    private array $parents;

    #[Subject, BeforeMethods('stackEmpty')]
    public function benchStackEmpty(): void
    {
        $token = $this->storage->attach($this->scope);
        $this->storage->detach($token);
    }

    #[Subject, BeforeMethods('stackNested')]
    public function benchStackNested(): void
    {
        $token = $this->storage->attach($this->scope);
        $this->storage->detach($token);
    }

    #[Subject, BeforeMethods('compositeEmpty')]
    public function benchCompositeEmpty(): void
    {
        $token = $this->storage->attach($this->scope);
        $this->storage->detach($token);
    }

    #[Subject, BeforeMethods('compositeNested')]
    public function benchCompositeNested(): void
    {
        $token = $this->storage->attach($this->scope);
        $this->storage->detach($token);
    }

    public function stackEmpty(): void
    {
        $this->setUp(false, 0);
    }
    public function stackNested(): void
    {
        $this->setUp(false, 63);
    }
    public function compositeEmpty(): void
    {
        $this->setUp(true, 0);
    }
    public function compositeNested(): void
    {
        $this->setUp(true, 63);
    }

    private function setUp(bool $composite, int $depth): void
    {
        $stack = new StackContextStorage();
        $this->storage = $composite
            ? new CompositeContextStorage($stack, new StackContextStorage(), new StackContextStorage(), new FiberRuntimeDetector())
            : $stack;
        $execution = Execution::start(
            ExecutionId::fromString('bench-context'),
            ExecutionDefinition::create(ExecutionName::fromString('operation'), ExecutionKind::fromString('task')),
            Instant::of(1),
        );
        $this->scope = new RunItTestKit()->buildScope($execution, ExecutionContext::empty());
        $this->parents = [];
        for ($i = 0; $i < $depth; ++$i) {
            $this->parents[] = $this->storage->attach($this->scope);
        }
    }

    public function verify(): void
    {
        $expected = $this->parents === [] ? null : $this->scope;
        if ($this->storage->current() !== $expected) {
            throw new RuntimeException('Context was not restored after attach/detach.');
        }
        while ($this->parents !== []) {
            $this->storage->detach(array_pop($this->parents));
        }
        if ($this->storage->current() !== null) {
            throw new RuntimeException('Context was not empty after parent cleanup.');
        }
    }
}
