<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FeatureFlagSchema;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\ValidationException;

/**
 * @covers \ItPack\ConfigureIt\Adapter\FeatureFlagSchema
 */
final class FeatureFlagSchemaTest extends Unit
{
    /**
     * @param array<string, mixed> $config
     * @return object{feature_flags: array<string, mixed>}
     */
    private function process(array $config): object
    {
        $result = new Processor()->process(
            Expect::structure(['feature_flags' => FeatureFlagSchema::structure()]),
            ['feature_flags' => $config],
        );

        /** @var object{feature_flags: array<string, mixed>} $result */
        return $result;
    }

    public function testBoolShortcutIsAccepted(): void
    {
        $result = $this->process(['newDashboard' => true]);

        verify($result->feature_flags['newDashboard'])->true();
    }

    public function testFullDefinitionAppliesDefaults(): void
    {
        $result = $this->process([
            'betaApi' => [
                'enabled' => true,
                'environments' => ['staging'],
                'tenants' => ['acme'],
                'attributes' => ['plan' => 'enterprise'],
                'rollout' => 25,
                'bucketBy' => 'user',
            ],
        ]);

        /** @var \stdClass $flag */
        $flag = $result->feature_flags['betaApi'];
        verify($flag->enabled)->true();
        verify($flag->environments)->equals(['staging']);
        verify($flag->tenants)->equals(['acme']);
        verify($flag->attributes)->equals(['plan' => 'enterprise']);
        verify($flag->rollout)->equals(25);
        verify($flag->bucketBy)->equals('user');
    }

    public function testInvalidFlagTypeProducesIssues(): void
    {
        $this->expectException(ValidationException::class);

        $this->process(['broken' => 42]);
    }

    public function testInvalidRolloutProducesIssues(): void
    {
        $this->expectException(ValidationException::class);

        $this->process(['broken' => ['enabled' => true, 'rollout' => 200]]);
    }

    public function testInvalidBucketByProducesIssues(): void
    {
        $this->expectException(ValidationException::class);

        $this->process(['broken' => ['enabled' => true, 'bucketBy' => 'galaxy']]);
    }

    public function testEmptyMappingIsValid(): void
    {
        $result = $this->process([]);

        verify($result->feature_flags)->equals([]);
    }
}
