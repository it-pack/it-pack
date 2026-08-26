<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use function array_unique;

use Codeception\Test\Unit;

use function count;

use InvalidArgumentException;
use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use ItPack\ConfigureIt\ValueObject\FlagDefinition;
use stdClass;

/**
 * M7 / E8.2 — flag definition parsing and per-context evaluation.
 */
class FlagDefinitionTest extends Unit
{
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
        $this->expectException(InvalidArgumentException::class);

        FlagDefinition::fromArray('x', ['enabled' => true, 'bucketBy' => 'nope']);
    }

    public function testInvalidRolloutThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FlagDefinition::fromArray('x', ['enabled' => true, 'rollout' => 150]);
    }

    public function testMalformedDataThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FlagDefinition::fromArray('x', 42);
    }

    public function testConstructorDefaultsToAnOffFlagWithFullRollout(): void
    {
        $def = new FlagDefinition('x');

        verify($def->name)->equals('x');
        verify($def->enabled)->false();
        verify($def->environments)->equals([]);
        verify($def->tenants)->equals([]);
        verify($def->attributes)->equals([]);
        verify($def->rollout)->equals(100);
        verify($def->bucketBy)->equals('tenant');
        verify($def->isEnabledFor($this->ctx()))->false();
    }

    public function testFromArrayDefaultsAnAbsentEnabledFlagToOff(): void
    {
        $def = FlagDefinition::fromArray('x', []);

        verify($def->enabled)->false();
        verify($def->rollout)->equals(100);
        verify($def->bucketBy)->equals('tenant');
    }

    /**
     * @param mixed $data
     *
     * @dataProvider coercedDefinitions
     */
    public function testFromArrayCoercesLooseConfigurationValues(mixed $data, callable $assert): void
    {
        $assert(FlagDefinition::fromArray('x', $data));
    }

    /**
     * @return array<string, array{mixed, callable(FlagDefinition): mixed}>
     */
    public function coercedDefinitions(): array
    {
        return [
            'truthy enabled string' => [
                ['enabled' => 'yes'],
                static fn (FlagDefinition $d): mixed => verify($d->enabled)->true(),
            ],
            'falsy enabled string' => [
                ['enabled' => ''],
                static fn (FlagDefinition $d): mixed => verify($d->enabled)->false(),
            ],
            'enabled from int' => [
                ['enabled' => 1],
                static fn (FlagDefinition $d): mixed => verify($d->enabled)->true(),
            ],
            'numeric-string rollout' => [
                ['rollout' => '50'],
                static fn (FlagDefinition $d): mixed => verify($d->rollout)->equals(50),
            ],
            'float rollout truncates' => [
                ['rollout' => 50.9],
                static fn (FlagDefinition $d): mixed => verify($d->rollout)->equals(50),
            ],
            'zero rollout' => [
                ['rollout' => 0],
                static fn (FlagDefinition $d): mixed => verify($d->rollout)->equals(0),
            ],
            'scalar environments become strings' => [
                ['environments' => [1, 2.5, true]],
                static fn (FlagDefinition $d): mixed => verify($d->environments)->equals(['1', '2.5', '1']),
            ],
            'null environment becomes empty string' => [
                ['environments' => [null]],
                static fn (FlagDefinition $d): mixed => verify($d->environments)->equals(['']),
            ],
            'scalar tenants become strings' => [
                ['tenants' => [7, 'acme']],
                static fn (FlagDefinition $d): mixed => verify($d->tenants)->equals(['7', 'acme']),
            ],
            'scalar attribute values become strings' => [
                ['attributes' => ['plan' => 5, 'beta' => true]],
                static fn (FlagDefinition $d): mixed => verify($d->attributes)->equals(['plan' => '5', 'beta' => '1']),
            ],
            'int attribute keys become strings' => [
                ['attributes' => [0 => 'v']],
                static fn (FlagDefinition $d): mixed => verify($d->attributes)->equals(['0' => 'v']),
            ],
            'null attribute value becomes empty string' => [
                ['attributes' => ['plan' => null]],
                static fn (FlagDefinition $d): mixed => verify($d->attributes)->equals(['plan' => '']),
            ],
            // A present-but-null bucketBy is treated as absent.
            'null bucketBy falls back to tenant' => [
                ['bucketBy' => null],
                static fn (FlagDefinition $d): mixed => verify($d->bucketBy)->equals('tenant'),
            ],
            'explicit user bucketBy' => [
                ['bucketBy' => 'user'],
                static fn (FlagDefinition $d): mixed => verify($d->bucketBy)->equals('user'),
            ],
            'explicit environment bucketBy' => [
                ['bucketBy' => 'environment'],
                static fn (FlagDefinition $d): mixed => verify($d->bucketBy)->equals('environment'),
            ],
        ];
    }

    /**
     * @param mixed $data
     *
     * @dataProvider rejectedDefinitions
     */
    public function testFromArrayRejectsMalformedDefinitions(mixed $data, string $expectedMessageFragment): void
    {
        try {
            FlagDefinition::fromArray('x', $data);
            self::fail('Expected the malformed flag definition to be rejected.');
        } catch (InvalidArgumentException $e) {
            verify(str_contains($e->getMessage(), $expectedMessageFragment))->true();
        }
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public function rejectedDefinitions(): array
    {
        return [
            'string payload'          => ['nope', 'must be a bool or an array definition'],
            'int payload'             => [42, 'must be a bool or an array definition'],
            'null payload'            => [null, 'must be a bool or an array definition'],
            'float payload'           => [1.5, 'must be a bool or an array definition'],

            'scalar environments'     => [['environments' => 'production'], 'must be a list of strings'],
            'scalar tenants'          => [['tenants' => 'acme'], 'must be a list of strings'],
            'nested environment'      => [['environments' => [['nested']]], 'must be a list of scalars'],
            'nested tenant'           => [['tenants' => [['nested']]], 'must be a list of scalars'],
            'object environment'      => [['environments' => [new stdClass()]], 'must be a list of scalars'],

            'scalar attributes'       => [['attributes' => 'plan=enterprise'], 'must be a map of string => string'],
            'nested attribute value'  => [['attributes' => ['plan' => ['a']]], 'attribute values must be scalars'],
            'object attribute value'  => [['attributes' => ['plan' => new stdClass()]], 'attribute values must be scalars'],

            'invalid bucketBy'        => [['bucketBy' => 'nope'], 'invalid bucketBy'],
            'non-string bucketBy'     => [['bucketBy' => 5], 'invalid bucketBy'],
            'array bucketBy'          => [['bucketBy' => ['tenant']], 'invalid bucketBy'],

            'non-numeric rollout'     => [['rollout' => 'half'], 'rollout must be numeric'],
            'null-ish rollout'        => [['rollout' => []], 'rollout must be numeric'],
            'rollout above range'     => [['rollout' => 150], 'rollout must be between 0 and 100'],
            'rollout below range'     => [['rollout' => -1], 'rollout must be between 0 and 100'],
        ];
    }

    public function testRolloutIsSkippedWhenTheBucketingUnitIsAbsent(): void
    {
        // A partial rollout bucketed by user cannot decide without a user, so the
        // flag stays off rather than defaulting to on.
        $byUser = FlagDefinition::fromArray('x', ['enabled' => true, 'rollout' => 50, 'bucketBy' => 'user']);

        verify($byUser->isEnabledFor($this->ctx('production')))->false();
        verify($byUser->isEnabledFor($this->ctx('production', ['tenant' => 'acme'])))->false();
    }

    public function testPartialRolloutBucketedByUserIsStable(): void
    {
        $def = FlagDefinition::fromArray('x', ['enabled' => true, 'rollout' => 50, 'bucketBy' => 'user']);
        $ctx = $this->ctx('production', ['user' => 'u-1']);

        verify($def->isEnabledFor($ctx))->equals($def->isEnabledFor($ctx));

        $enabled = 0;

        foreach (range(1, 200) as $i) {
            if ($def->isEnabledFor($this->ctx('production', ['user' => 'user-' . $i]))) {
                ++$enabled;
            }
        }

        self::assertGreaterThan(80, $enabled);
        self::assertLessThan(120, $enabled);
    }

    public function testPartialRolloutBucketedByEnvironmentIsStable(): void
    {
        $def = FlagDefinition::fromArray('x', ['enabled' => true, 'rollout' => 50, 'bucketBy' => 'environment']);

        verify($def->isEnabledFor($this->ctx('staging')))->equals($def->isEnabledFor($this->ctx('staging')));

        $enabled = 0;

        foreach (range(1, 200) as $i) {
            if ($def->isEnabledFor($this->ctx('env-' . $i))) {
                ++$enabled;
            }
        }

        self::assertGreaterThan(80, $enabled);
        self::assertLessThan(120, $enabled);
    }

    public function testTenantBucketingFallsBackToTheEnvironment(): void
    {
        $def = FlagDefinition::fromArray('x', ['enabled' => true, 'rollout' => 50, 'bucketBy' => 'tenant']);

        // Without a tenant the environment is the bucketing unit, so the
        // decision is still deterministic (not simply off).
        $first = $def->isEnabledFor($this->ctx('production'));

        verify($def->isEnabledFor($this->ctx('production')))->equals($first);
    }

    public function testDifferentFlagNamesBucketIndependently(): void
    {
        $ctx = $this->ctx('production', ['tenant' => 'acme']);

        $decisions = [];

        foreach (['flag-a', 'flag-b', 'flag-c', 'flag-d', 'flag-e', 'flag-f'] as $name) {
            $decisions[] = FlagDefinition::fromArray($name, [
                'enabled' => true,
                'rollout' => 50,
            ])->isEnabledFor($ctx);
        }

        // The (unit, flag) pair is hashed, so one tenant does not get an
        // all-or-nothing experience across independent flags.
        verify(count(array_unique($decisions)))->equals(2);
    }

    public function testGatesAreEvaluatedInDeclaredOrder(): void
    {
        $def = FlagDefinition::fromArray('x', [
            'enabled' => true,
            'environments' => ['staging'],
            'tenants' => ['acme'],
            'attributes' => ['plan' => 'enterprise'],
            'rollout' => 100,
        ]);

        verify($def->isEnabledFor($this->ctx('staging', ['tenant' => 'acme', 'plan' => 'enterprise'])))->true();
        // Each gate alone is enough to switch the flag off.
        verify($def->isEnabledFor($this->ctx('production', ['tenant' => 'acme', 'plan' => 'enterprise'])))->false();
        verify($def->isEnabledFor($this->ctx('staging', ['tenant' => 'other', 'plan' => 'enterprise'])))->false();
        verify($def->isEnabledFor($this->ctx('staging', ['tenant' => 'acme', 'plan' => 'free'])))->false();
        verify($def->isEnabledFor($this->ctx('staging', ['tenant' => 'acme'])))->false();
    }
    /**
     * @param array<string, string> $attributes
     */
    private function ctx(string $environment = 'production', array $attributes = []): ExecutionContextInterface
    {
        return new ExecutionContext($environment, $attributes);
    }
}
