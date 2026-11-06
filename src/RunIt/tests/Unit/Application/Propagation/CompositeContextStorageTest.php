<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Propagation;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Propagation\CompositeContextStorage;
use ItPack\RunIt\Application\Propagation\CompositeContextToken;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Contract\Port\RuntimeContextDetectorInterface;
use ItPack\RunIt\Domain\Exception\InvalidContextTokenException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use Tests\Support\Fixture\FakeContextToken;
use Tests\Support\Fixture\RunItTestKit;

/**
 * M11.2 — runtime-aware composite context storage.
 */
final class CompositeContextStorageTest extends Unit
{
    private function definition(): ExecutionDefinitionInterface
    {
        return ExecutionDefinition::create(ExecutionName::fromString('test'), ExecutionKind::fromString('composite'));
    }

    private function scope(): ExecutionScopeInterface
    {
        $kit = new RunItTestKit();
        $captured = null;
        $kit->runner->run($this->definition(), static function (ExecutionScopeInterface $scope) use (&$captured): void {
            $captured = $scope;
        });

        self::assertInstanceOf(ExecutionScopeInterface::class, $captured);

        return $captured;
    }

    /**
     * @return array{0: FakeStorage, 1: FakeStorage, 2: FakeStorage, 3: CompositeContextStorage}
     */
    private function build(RuntimeContextDetectorInterface $detector): array
    {
        $sync = new FakeStorage();
        $fiber = new FakeStorage();
        $coroutine = new FakeStorage();
        $composite = new CompositeContextStorage($sync, $fiber, $coroutine, $detector);

        return [$sync, $fiber, $coroutine, $composite];
    }

    public function testFallsBackToSynchronousStorage(): void
    {
        [$sync, , , $composite] = $this->build(new FakeDetector(coroutine: false, fiber: false));
        $scope = $this->scope();

        $token = $composite->attach($scope);

        self::assertInstanceOf(CompositeContextToken::class, $token);
        self::assertSame(0, $token->id(), 'The composite exposes its custom inner token identity.');
        self::assertSame($scope, $sync->current());

        $composite->detach($token);
        self::assertNull($sync->current());
    }

    public function testSelectsFiberStorage(): void
    {
        [, $fiber, , $composite] = $this->build(new FakeDetector(coroutine: false, fiber: true));
        $scope = $this->scope();

        $token = $composite->attach($scope);

        self::assertSame($scope, $fiber->current());

        $composite->detach($token);
        self::assertNull($fiber->current());
    }

    public function testSelectsCoroutineStorage(): void
    {
        [, , $coroutine, $composite] = $this->build(new FakeDetector(coroutine: true, fiber: false));
        $scope = $this->scope();

        $token = $composite->attach($scope);

        self::assertSame($scope, $coroutine->current());

        $composite->detach($token);
        self::assertNull($coroutine->current());
    }

    public function testForeignTokenIsRejected(): void
    {
        [, , , $composite] = $this->build(new FakeDetector(coroutine: false, fiber: false));

        $this->expectException(InvalidContextTokenException::class);
        $composite->detach(new FakeContextToken());
    }

    public function testForeignCompositeTokenIsRejectedEvenWithSharedChild(): void
    {
        $child = new StrictStackStorage();
        $detector = new FakeDetector();
        $first = new CompositeContextStorage($child, $child, $child, $detector);
        $second = new CompositeContextStorage($child, $child, $child, $detector);
        $scope = $this->scope();
        $token = $first->attach($scope);

        try {
            $second->detach($token);
            self::fail('A foreign composite token must be rejected.');
        } catch (InvalidContextTokenException) {
            self::assertSame($scope, $child->current());
        }

        $first->detach($token);
        self::assertNull($child->current());
    }

    public function testCloneHasIndependentTokenOwnership(): void
    {
        $child = new StrictStackStorage();
        $original = new CompositeContextStorage($child, $child, $child, new FakeDetector());
        $scope = $this->scope();
        $token = $original->attach($scope);
        $clone = clone $original;

        try {
            $clone->detach($token);
            self::fail('A clone must reject a token issued by the original.');
        } catch (InvalidContextTokenException) {
            self::assertSame($scope, $child->current());
        }

        $original->detach($token);
        self::assertNull($child->current());

        $cloneToken = $clone->attach($scope);
        try {
            $original->detach($cloneToken);
            self::fail('The original must reject a token issued by its clone.');
        } catch (InvalidContextTokenException) {
            self::assertSame($scope, $child->current());
        }

        $clone->detach($cloneToken);
        self::assertNull($child->current());
    }

    public function testForgedWrapperIsRejectedWithoutDetachingInnerToken(): void
    {
        $child = new StrictStackStorage();
        $composite = new CompositeContextStorage($child, $child, $child, new FakeDetector());
        $scope = $this->scope();
        $token = $composite->attach($scope);
        self::assertInstanceOf(CompositeContextToken::class, $token);

        try {
            $composite->detach(new CompositeContextToken($token->owner, $token->inner));
            self::fail('A forged wrapper must be rejected.');
        } catch (InvalidContextTokenException) {
            self::assertSame($scope, $child->current());
        }

        $composite->detach($token);
        self::assertNull($child->current());
    }

    public function testCollidingTokensRetryOutOfOrderAndRejectRepeatedDetach(): void
    {
        $child = new StrictStackStorage();
        $composite = new CompositeContextStorage($child, $child, $child, new FakeDetector());
        $outerScope = $this->scope();
        $innerScope = $this->scope();
        $outer = $composite->attach($outerScope);
        $inner = $composite->attach($innerScope);
        self::assertSame($outer->id(), $inner->id());

        try {
            $composite->detach($outer);
            self::fail('An out-of-order token must be rejected by its owner.');
        } catch (InvalidContextTokenException) {
            self::assertSame($innerScope, $child->current());
        }

        $composite->detach($inner);

        try {
            $composite->detach($inner);
            self::fail('A detached token must not be accepted again.');
        } catch (InvalidContextTokenException) {
            self::assertSame($outerScope, $child->current());
        }

        $composite->detach($outer);
        self::assertNull($child->current());
    }

    public function testDetachUsesOriginalOwnerAfterDetectorSwitches(): void
    {
        $sync = new StrictStackStorage();
        $fiber = new StrictStackStorage();
        $coroutine = new StrictStackStorage();
        $detector = new FakeDetector();
        $composite = new CompositeContextStorage($sync, $fiber, $coroutine, $detector);
        $syncScope = $this->scope();
        $fiberScope = $this->scope();
        $syncToken = $composite->attach($syncScope);
        $detector->fiber = true;
        $fiberToken = $composite->attach($fiberScope);
        self::assertSame($syncToken->id(), $fiberToken->id());
        $detector->coroutine = true;

        $composite->detach($syncToken);
        self::assertNull($sync->current());
        self::assertSame($fiberScope, $fiber->current());
        $composite->detach($fiberToken);
        self::assertNull($fiber->current());
        self::assertNull($coroutine->current());
    }
}

/** A LIFO storage whose distinct tokens deliberately have the same id. */
final class StrictStackStorage implements ContextStorageInterface
{
    /** @var list<array{ContextTokenInterface, ExecutionScopeInterface}> */
    private array $stack = [];

    public function current(): ?ExecutionScopeInterface
    {
        return $this->stack === [] ? null : $this->stack[array_key_last($this->stack)][1];
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        $token = new FakeContextToken();
        $this->stack[] = [$token, $scope];

        return $token;
    }

    public function detach(ContextTokenInterface $token): void
    {
        if ($this->stack === [] || $this->stack[array_key_last($this->stack)][0] !== $token) {
            throw InvalidContextTokenException::mismatched();
        }

        array_pop($this->stack);
    }
}

final class FakeStorage implements ContextStorageInterface
{
    public ?ExecutionScopeInterface $currentScope = null;

    public function current(): ?ExecutionScopeInterface
    {
        return $this->currentScope;
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        $this->currentScope = $scope;

        return new FakeContextToken();
    }

    public function detach(ContextTokenInterface $token): void
    {
        $this->currentScope = null;
    }
}

final class FakeDetector implements RuntimeContextDetectorInterface
{
    public function __construct(
        public bool $coroutine = false,
        public bool $fiber = false,
    ) {
    }

    public function inCoroutine(): bool
    {
        return $this->coroutine;
    }

    public function inFiber(): bool
    {
        return $this->fiber;
    }
}
