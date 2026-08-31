<?php

declare(strict_types=1);

namespace Tests\Functional;

use Codeception\Test\Unit;
use Fiber;
use ItPack\CommandIt\Adapter\Context\AmbientContextStorage;
use ItPack\CommandIt\Adapter\Context\FiberBoundContextStorage;
use ItPack\CommandIt\Contract\Domain\Classification;
use ItPack\CommandIt\Contract\Exception\InvalidContextTokenException;
use ItPack\CommandIt\Contract\Port\RuntimeContextDetectorInterface;
use ItPack\CommandIt\Domain\CommandContext;

/**
 * @covers \ItPack\CommandIt\Adapter\Context\FiberBoundContextStorage
 *
 * This runtime's `Fiber::resume()` / `Fiber::suspend()` are unavailable, so real
 * concurrently-alive fibers cannot be simulated. Isolation is instead validated
 * by injecting a fake detector that returns distinct (unstarted) Fiber anchors
 * to emulate separate fibers, proving the WeakMap keying isolates contexts
 * per fiber and never leaks into the main (ambient) stack.
 */
final class FiberBoundContextStorageTest extends Unit
{
    private function context(string $value): CommandContext
    {
        return new CommandContext()->with('k', $value, Classification::Internal);
    }

    private function currentValue(FiberBoundContextStorage $storage, string $key): mixed
    {
        $context = $storage->current();
        self::assertNotNull($context);

        return $context->get($key);
    }

    public function testMainStackUsesAmbientAndIsolatesFromFibers(): void
    {
        $detector = new FakeFiberDetector();
        $storage = new FiberBoundContextStorage($detector, new AmbientContextStorage());

        $detector->setCurrent(null);
        $mainToken = $storage->enter($this->context('main'));
        self::assertSame('main', $this->currentValue($storage, 'k'));

        $detector->setCurrent(new Fiber(static fn () => null));
        $storage->enter($this->context('fiber'));
        self::assertSame('fiber', $this->currentValue($storage, 'k'), 'fiber sees only its own context');
        $detector->setCurrent(null);

        self::assertSame('main', $this->currentValue($storage, 'k'), 'main context unaffected by fiber');

        $storage->leave($mainToken);
        self::assertNull($storage->current());
    }

    public function testEachFiberHasIndependentContext(): void
    {
        $detector = new FakeFiberDetector();
        $storage = new FiberBoundContextStorage($detector, new AmbientContextStorage());

        $fiberA = new Fiber(static fn () => null);
        $fiberB = new Fiber(static fn () => null);

        $detector->setCurrent($fiberA);
        $tokenA = $storage->enter($this->context('A'));
        self::assertSame('A', $this->currentValue($storage, 'k'));

        $detector->setCurrent($fiberB);
        $tokenB = $storage->enter($this->context('B'));
        self::assertSame('B', $this->currentValue($storage, 'k'));

        // Switch back to A: its context must be preserved, not overwritten by B.
        $detector->setCurrent($fiberA);
        self::assertSame('A', $this->currentValue($storage, 'k'));

        // Leaving B must not disturb A.
        $detector->setCurrent($fiberB);
        $storage->leave($tokenB);
        $detector->setCurrent($fiberA);
        self::assertSame('A', $this->currentValue($storage, 'k'));

        $storage->leave($tokenA);
        $detector->setCurrent(null);
        self::assertNull($storage->current(), 'main ambient untouched');
    }

    public function testNewFiberStartsWithFreshContext(): void
    {
        $detector = new FakeFiberDetector();
        $storage = new FiberBoundContextStorage($detector, new AmbientContextStorage());

        $fiberA = new Fiber(static fn () => null);
        $detector->setCurrent($fiberA);
        $storage->enter($this->context('A'));

        $fresh = new Fiber(static fn () => null);
        $detector->setCurrent($fresh);
        self::assertNull($storage->current(), 'fresh fiber inherits no context (no leakage)');

        $detector->setCurrent(null);
        self::assertNull($storage->current());
    }

    public function testContextIsRestoredOnLeave(): void
    {
        $detector = new FakeFiberDetector();
        $storage = new FiberBoundContextStorage($detector, new AmbientContextStorage());
        $detector->setCurrent(null);

        $outer = $storage->enter($this->context('outer'));

        try {
            $inner = $storage->enter($this->context('inner'));

            try {
                self::assertSame('inner', $this->currentValue($storage, 'k'));
            } finally {
                $storage->leave($inner);
            }

            self::assertSame('outer', $this->currentValue($storage, 'k'));
        } finally {
            $storage->leave($outer);
        }

        self::assertNull($storage->current());
    }

    public function testResetClearsMainStack(): void
    {
        $detector = new FakeFiberDetector();
        $storage = new FiberBoundContextStorage($detector, new AmbientContextStorage());
        $detector->setCurrent(null);
        $storage->enter($this->context('main'));

        self::assertSame('main', $this->currentValue($storage, 'k'));

        $storage->reset();

        self::assertNull($storage->current(), 'reset cleared the ambient (main) scope');
    }

    public function testResetClearsOnlyCallingFiber(): void
    {
        $detector = new FakeFiberDetector();
        $storage = new FiberBoundContextStorage($detector, new AmbientContextStorage());

        $fiberA = new Fiber(static fn () => null);
        $fiberB = new Fiber(static fn () => null);

        $detector->setCurrent($fiberA);
        $storage->enter($this->context('A'));
        $detector->setCurrent($fiberB);
        $storage->enter($this->context('B'));

        // reset() clears only the *calling* (B) fiber's stack.
        $storage->reset();

        $detector->setCurrent($fiberB);
        self::assertNull($storage->current(), 'fiber B cleared');
        $detector->setCurrent($fiberA);
        self::assertSame('A', $this->currentValue($storage, 'k'), 'fiber A untouched by B reset');
    }

    public function testPopAfterResetRejectsTokenLoudly(): void
    {
        $detector = new FakeFiberDetector();
        $storage = new FiberBoundContextStorage($detector, new AmbientContextStorage());
        $detector->setCurrent(null);
        $token = $storage->enter($this->context('main'));

        $storage->reset();

        $this->expectException(InvalidContextTokenException::class);
        $storage->leave($token);
    }
}

/**
 * Test double: lets the test pin the "current fiber" anchor without resuming real fibers.
 *
 * @internal
 */
final class FakeFiberDetector implements RuntimeContextDetectorInterface
{
    /** @var \Fiber<mixed, mixed, mixed, mixed>|null */
    private ?Fiber $current = null;

    /**
     * @param \Fiber<mixed, mixed, mixed, mixed>|null $current
     */
    public function setCurrent(?Fiber $current): void
    {
        $this->current = $current;
    }

    /**
     * @return \Fiber<mixed, mixed, mixed, mixed>|null
     */
    public function currentFiber(): ?Fiber
    {
        return $this->current;
    }

    public function isCoroutineRuntime(): bool
    {
        return false;
    }
}
