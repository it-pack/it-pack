<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use ItPack\ConfigureIt\ValueObject\FlagDefinition;

/**
 * M7 / E8.2 — flag definition parsing and per-context evaluation.
 */
class FlagDefinitionTest extends Unit
{
    /**
     * @param array<string, string> $attributes
     */
    private function ctx(string $environment = 'production', array $attributes = []): ExecutionContextInterface
    {
        return new ExecutionContext($environment, $attributes);
    }

    public function testBoolShortcutEnablesAndDisables(): void
    {
        self::assertTrue(FlagDefinition::fromArray('x', true)->isEnabledFor($this->ctx()));
        self::assertFalse(FlagDefinition::fromArray('x', false)->isEnabledFor($this->ctx()));
    }

    public function testDisabledMasterSwitchBeatsEverything(): void
    {
        $def = FlagDefinition::fromArray('x', [
            'enabled' => false,
            'environments' => ['production'],
            'rollout' => 100,
        ]);

        self::assertFalse($def->isEnabledFor($this->ctx('production')));
    }

    public function testEnvironmentAllowListGates(): void
    {
        $def = FlagDefinition::fromArray('x', [
            'enabled' => true,
            'environments' => ['staging', 'canary'],
        ]);

        self::assertTrue($def->isEnabledFor($this->ctx('staging')));
        self::assertFalse($def->isEnabledFor($this->ctx('production')));
    }

    public function testTenantAllowListGates(): void
    {
        $def = FlagDefinition::fromArray('x', [
            'enabled' => true,
            'tenants' => ['acme', 'globex'],
        ]);

        self::assertTrue($def->isEnabledFor($this->ctx('production', ['tenant' => 'acme'])));
        self::assertFalse($def->isEnabledFor($this->ctx('production', ['tenant' => 'initech'])));
        self::assertFalse($def->isEnabledFor($this->ctx('production')));
    }

    public function testAttributeMatchGates(): void
    {
        $def = FlagDefinition::fromArray('x', [
            'enabled' => true,
            'attributes' => ['plan' => 'enterprise', 'region' => 'eu'],
        ]);

        self::assertTrue($def->isEnabledFor($this->ctx('production', ['plan' => 'enterprise', 'region' => 'eu'])));
        self::assertFalse($def->isEnabledFor($this->ctx('production', ['plan' => 'enterprise', 'region' => 'us'])));
    }

    public function testRolloutIsStableAndPercentageBased(): void
    {
        $def = FlagDefinition::fromArray('x', [
            'enabled' => true,
            'rollout' => 50,
            'bucketBy' => 'tenant',
        ]);

        // Same tenant is always consistent across calls.
        $acme = $this->ctx('production', ['tenant' => 'acme']);
        self::assertSame($def->isEnabledFor($acme), $def->isEnabledFor($acme));

        // For a 50% rollout, the deterministic bucket yields a stable split
        // across a set of tenants.
        $enabled = 0;

        foreach (range(1, 200) as $i) {
            if ($def->isEnabledFor($this->ctx('production', ['tenant' => 'tenant-' . $i]))) {
                ++$enabled;
            }
        }

        // 50% of 200 => 100, plus/minus tiny hashing skew.
        self::assertGreaterThan(80, $enabled);
        self::assertLessThan(120, $enabled);
    }

    public function testRolloutBoundaries(): void
    {
        self::assertTrue(FlagDefinition::fromArray('x', ['enabled' => true, 'rollout' => 100])->isEnabledFor($this->ctx()));
        self::assertFalse(FlagDefinition::fromArray('x', ['enabled' => true, 'rollout' => 0])->isEnabledFor($this->ctx()));
    }

    public function testBucketByUserAndEnvironment(): void
    {
        $byUser = FlagDefinition::fromArray('x', ['enabled' => true, 'rollout' => 100, 'bucketBy' => 'user']);
        self::assertTrue($byUser->isEnabledFor($this->ctx('production', ['user' => 'u1'])));

        $byEnv = FlagDefinition::fromArray('x', ['enabled' => true, 'environments' => ['production']]);
        self::assertTrue($byEnv->isEnabledFor($this->ctx('production')));
    }

    public function testInvalidBucketByThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FlagDefinition::fromArray('x', ['enabled' => true, 'bucketBy' => 'nope']);
    }

    public function testInvalidRolloutThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FlagDefinition::fromArray('x', ['enabled' => true, 'rollout' => 150]);
    }

    public function testMalformedDataThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FlagDefinition::fromArray('x', 42);
    }
}
