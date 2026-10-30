<?php

declare(strict_types=1);

namespace Tests\Conformance;

use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\Conformance\Fixture\AmpRuntimeFixture;
use ItPack\AsyncIt\Conformance\Fixture\NativeRuntimeFixture;
use ItPack\AsyncIt\Conformance\Fixture\ReactPhpRuntimeFixture;
use ItPack\AsyncIt\Conformance\Fixture\RevoltRuntimeFixture;
use ItPack\AsyncIt\Conformance\Fixture\SwooleRuntimeFixture;
use ItPack\AsyncIt\Conformance\Fixture\SwowRuntimeFixture;
use ItPack\AsyncIt\Conformance\Verifier\AsyncRuntimeVerifier;
use ItPack\AsyncIt\Conformance\Verifier\ContextIsolationVerifier;
use ItPack\AsyncIt\Conformance\Verifier\DeterministicCleanupVerifier;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\RunIt\Conformance\Verifier\ExecutionEngineVerifier;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Revolt\EventLoop;
use Swoole\Coroutine;

/**
 * Phase 4 gate: every available native runtime adapter must pass the same
 * AsyncIt conformance suite (Assertions A + C) and RunIt's ExecutionEngineVerifier.
 *
 * Revolt/Swow are negotiated-only; their fixtures are exercised when the
 * runtime is present and skipped otherwise.
 *
 * @covers \ItPack\AsyncIt\Conformance\Verifier\AsyncRuntimeVerifier
 */
final class RuntimeAdapterConformanceTest extends TestCase
{
    public function testSwooleAdapterConforms(): void
    {
        if (!class_exists(Coroutine::class)) {
            self::markTestSkipped('Swoole runtime not available.');
        }

        $result = $this->verifier()->verify(new SwooleRuntimeFixture());

        self::assertTrue($result->isConformant(), $result->summary());
    }

    public function testRevoltAdapterConforms(): void
    {
        if (!class_exists(EventLoop::class)) {
            self::markTestSkipped('Revolt runtime not available.');
        }

        $result = $this->verifier()->verify(new RevoltRuntimeFixture());

        self::assertTrue($result->isConformant(), $result->summary());
    }

    public function testSwowAdapterConforms(): void
    {
        if (!class_exists('Swow\\Coroutine')) {
            self::markTestSkipped('Swow runtime not available.');
        }

        $result = $this->verifier()->verify(new SwowRuntimeFixture());

        self::assertTrue($result->isConformant(), $result->summary());
    }

    public function testNativePollerConforms(): void
    {
        if (!class_exists('Io\\Poll')) {
            self::markTestSkipped('PHP 8.6 Io\\Poll not available.');
        }

        $result = $this->verifier()->verify(new NativeRuntimeFixture());

        self::assertTrue($result->isConformant(), $result->summary());
    }

    public function testReactPhpInteropConforms(): void
    {
        // @phpstan-ignore-next-line function.impossibleType (ReactPHP is an optional dependency)
        if (!class_exists(LoopInterface::class)) {
            self::markTestSkipped('ReactPHP not available.');
        }

        $loopClass = Loop::class;
        /** @var callable $get */
        $get = $loopClass . '::get';
        /** @var object $loop */
        $loop = $get();

        $result = $this->verifier()->verify(new ReactPhpRuntimeFixture($loop));

        self::assertTrue($result->isConformant(), $result->summary());
    }

    public function testAmpInteropConforms(): void
    {
        if (!class_exists('Amp\\Loop')) {
            self::markTestSkipped('Amp not available.');
        }

        $result = $this->verifier()->verify(new AmpRuntimeFixture());

        self::assertTrue($result->isConformant(), $result->summary());
    }

    public function testNegotiatedEngineConformsToRunIt(): void
    {
        $result = new ExecutionEngineVerifier()->verify(
            AsyncRuntime::discover(),
            new ReferenceExecutionScope('engine'),
        );

        self::assertTrue($result->isConformant(), $result->summary());
    }

    private function verifier(): AsyncRuntimeVerifier
    {
        return new AsyncRuntimeVerifier(new ContextIsolationVerifier(), new DeterministicCleanupVerifier());
    }
}
