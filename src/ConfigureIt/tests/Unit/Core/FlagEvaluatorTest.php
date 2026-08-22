<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
use ItPack\ConfigureIt\Core\ConfigurationSnapshot;
use ItPack\ConfigureIt\Core\FlagEvaluator;
use ItPack\ConfigureIt\Execution\ExecutionContext;

/**
 * M7 / E8.2 — FlagEvaluator reads flag definitions from the resolved snapshot
 * and evaluates them against an execution context.
 */
class FlagEvaluatorTest extends Unit
{
    /**
     * @param array<string, string> $attributes
     */
    private function ctx(string $environment = 'production', array $attributes = []): ExecutionContextInterface
    {
        return new ExecutionContext($environment, $attributes);
    }

    /**
     * @param array<string, mixed> $flags
     */
    private function snapshot(array $flags): ConfigurationSnapshot
    {
        return new ConfigurationSnapshot(['feature_flags' => $flags]);
    }

    public function testNoFlagsYieldsEmptyState(): void
    {
        $evaluator = new FlagEvaluator(new ConfigurationSnapshot([]));

        self::assertSame([], $evaluator->definitions());
        self::assertFalse($evaluator->has('anything'));
        self::assertFalse($evaluator->isEnabled('anything', $this->ctx()));
        self::assertSame([], $evaluator->evaluate($this->ctx()));
    }

    public function testReadsDefinitionsFromSnapshot(): void
    {
        $evaluator = new FlagEvaluator($this->snapshot([
            'newDashboard' => true,
            'betaApi' => ['enabled' => true, 'tenants' => ['acme']],
        ]));

        self::assertTrue($evaluator->has('newDashboard'));
        self::assertTrue($evaluator->isEnabled('newDashboard', $this->ctx()));
        self::assertTrue($evaluator->isEnabled('betaApi', $this->ctx('production', ['tenant' => 'acme'])));
        self::assertFalse($evaluator->isEnabled('betaApi', $this->ctx('production', ['tenant' => 'other'])));
        self::assertSame(
            ['newDashboard' => true, 'betaApi' => false],
            $evaluator->evaluate($this->ctx('production', ['tenant' => 'other'])),
        );
    }

    public function testUnknownFlagIsOff(): void
    {
        $evaluator = new FlagEvaluator($this->snapshot(['on' => true]));

        self::assertFalse($evaluator->isEnabled('off', $this->ctx()));
    }

    public function testCustomPath(): void
    {
        $evaluator = new FlagEvaluator(
            new ConfigurationSnapshot(['toggles' => ['x' => true]]),
            'toggles',
        );

        self::assertTrue($evaluator->isEnabled('x', $this->ctx()));
    }

    public function testMalformedFlagsThrow(): void
    {
        $this->expectException(\LogicException::class);

        new FlagEvaluator(new ConfigurationSnapshot(['feature_flags' => 'not-a-map']));
    }
}
