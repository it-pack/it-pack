<?php

declare(strict_types=1);

namespace Tests\Integration;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Psr\Log\ObservationLoggerPublisher;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use Psr\Log\AbstractLogger;
use RuntimeException;
use stdClass;
use Stringable;
use Throwable;

final class DefaultSdkPsrSecurityTest extends Unit
{
    public function testDefaultPipelineProtectsObservationBeforeSamplingAndPsrLogging(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<array-key, mixed>}> */
            public array $entries = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->entries[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
        $sampler = new class () implements SamplingPolicyInterface {
            /** @var list<array<array-key, mixed>> */
            public array $seen = [];

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->seen[] = $artifact->normalize();
                return true;
            }
        };
        $mapper = new class () implements ThrowableMapperInterface {
            public function describe(Throwable $throwable): ThrowableDescriptionInterface
            {
                return ThrowableDescription::create(
                    'RuntimeException',
                    'throwable-secret',
                    42,
                    ThrowableDescription::create('RuntimeException', 'cause-secret', 7, null, 'cause-trace-secret'),
                    'outer-trace-secret'
                );
            }
        };
        $runtime = RunItBuilder::create()
            ->withPublisher(new ObservationLoggerPublisher($logger))
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withSamplingPolicy($sampler)
            ->withThrowableMapper($mapper)
            ->build()
        ;
        $businessResult = new stdClass();
        $original = null;
        $result = $runtime->run(
            ExecutionDefinition::create(ExecutionName::fromString('test'), ExecutionKind::fromString('test')),
            static function (ExecutionScopeInterface $scope) use ($businessResult, &$original): object {
                $original = $scope->observations()->record(
                    'outer-message-secret',
                    ObservationSeverity::Error,
                    AttributeCollection::fromArray(['request' => ['password' => 'nested-attribute-secret']]),
                    new RuntimeException('business exception'),
                );
                return $businessResult;
            },
        );

        self::assertSame($businessResult, $result);
        self::assertNotNull($original);
        self::assertSame('outer-message-secret', $original->message());
        $request = $original->attributes()['request'];
        self::assertIsArray($request);
        self::assertSame('nested-attribute-secret', $request['password']);
        self::assertSame('outer-trace-secret', $original->throwableDescription()?->toArray()['trace']);
        $observations = array_values(array_filter($sampler->seen, static fn (array $fields): bool =>
            ($fields['severity'] ?? null) === 'error'));
        self::assertCount(1, $observations);
        $logs = array_values(array_filter($logger->entries, static fn (array $entry): bool =>
            ($entry['context']['severity'] ?? null) === 'error'));
        self::assertCount(1, $logs);
        self::assertSame('error', $logs[0]['level']);
        self::assertSame('[redacted]', $logs[0]['message']);
        self::assertSame($observations[0]['execution_id'], $logs[0]['context']['execution_id']);
        self::assertSame($observations[0]['id'], $logs[0]['context']['observation_id']);
        self::assertSame($observations[0]['sequence'], $logs[0]['context']['sequence']);
        self::assertSame($observations[0]['timestamp'], $logs[0]['context']['timestamp']);
        $exception = $logs[0]['context']['exception'];
        self::assertIsArray($exception);
        self::assertSame('RuntimeException', $exception['class']);
        self::assertSame(42, $exception['code']);
        self::assertIsArray($exception['previous']);
        self::assertSame('RuntimeException', $exception['previous']['class']);
        self::assertSame(7, $exception['previous']['code']);
        foreach (['outer-message-secret', 'nested-attribute-secret', 'throwable-secret', 'cause-secret',
            'outer-trace-secret', 'cause-trace-secret'] as $secret) {
            self::assertStringNotContainsString($secret, serialize($sampler->seen));
            self::assertStringNotContainsString($secret, serialize($logger->entries));
        }
    }
}
