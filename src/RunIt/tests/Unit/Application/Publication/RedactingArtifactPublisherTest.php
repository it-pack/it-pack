<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Execution\ExecutionSuccess;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\Support\Fixture\RecordingArtifactPublisher;
use Throwable;

#[CoversClass(RedactingArtifactPublisher::class)]
final class RedactingArtifactPublisherTest extends Unit
{
    public function testPublishWithoutRedactorPassesThrough(): void
    {
        $inner = $this->innerPublisher();
        $publisher = new RedactingArtifactPublisher(null, $inner);
        $artifact = $this->execution('succeeded');

        $publisher->publish($artifact);

        self::assertCount(1, $inner->published());
    }

    public function testPublishWithRedactorRedacts(): void
    {
        $redactor = new class () implements RedactorInterface {
            public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
            {
                return new readonly class () implements RedactedArtifactInterface {
                    public function artifactType(): string
                    {
                        return 'execution';
                    }

                    /** @return array<string, mixed> */
                    public function normalize(): array
                    {
                        return ['id' => 'redacted'];
                    }
                };
            }
        };

        $inner = $this->innerPublisher();
        $publisher = new RedactingArtifactPublisher($redactor, $inner);
        $artifact = $this->execution('succeeded');

        $publisher->publish($artifact);

        self::assertCount(1, $inner->published());
        self::assertSame(['id' => 'redacted'], $inner->published()[0]->normalize());
    }

    public function testConstructorThrowsWhenDropRedactedObservationsWithoutHealth(): void
    {
        try {
            new RedactingArtifactPublisher(
                null,
                $this->innerPublisher(),
                null,
                true,
            );
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'dropRedactedObservations requires a health reporter.',
                $invalidArgumentException->getMessage()
            );
        }
    }

    public function testDropRedactedObservationReportsAndDoesNotPublish(): void
    {
        $reporter = new class () implements InternalHealthReporterInterface {
            public bool $reported = false;

            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                $this->reported = true;
            }
        };

        $redactor = $this->createMock(RedactorInterface::class);
        $redactedArtifact = new readonly class (['id' => 'redacted']) implements RedactedArtifactInterface {
            /** @param array<string, mixed> $data */
            public function __construct(private array $data)
            {
            }

            public function artifactType(): string
            {
                return 'observation';
            }

            /** @return array<string, mixed> */
            public function normalize(): array
            {
                return $this->data;
            }
        };
        $redactor->method('redact')->willReturnCallback(fn (): ExecutionArtifactInterface => $redactedArtifact);

        $inner = $this->innerPublisher();
        $publisher = new RedactingArtifactPublisher(
            $redactor,
            $inner,
            $reporter,
            true,
        );

        $observation = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'test message',
        );

        $publisher->publish($observation);

        self::assertCount(0, $inner->published(), 'Artifact should have been dropped, not published');
        self::assertTrue($reporter->reported, 'Health reporter should have been called');
    }

    public function testFlushDelegatesToInner(): void
    {
        $inner = new class () implements ArtifactPublisherInterface, FlushablePublisherInterface {
            public function publish(ExecutionArtifactInterface $artifact): void
            {
            }

            public function flush(): void
            {
            }
        };
        $publisher = new RedactingArtifactPublisher(null, $inner);
        $publisher->flush();
    }

    public function testCloseDelegatesToInner(): void
    {
        $inner = new class () implements ArtifactPublisherInterface, CloseablePublisherInterface {
            public function publish(ExecutionArtifactInterface $artifact): void
            {
            }

            public function close(): void
            {
            }
        };
        $publisher = new RedactingArtifactPublisher(null, $inner);
        $publisher->close();
    }

    private function innerPublisher(): RecordingArtifactPublisher
    {
        return new RecordingArtifactPublisher();
    }

    private function execution(string $status): ExecutionInterface
    {
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task')
        );
        $execution = Execution::create(
            ExecutionId::fromString('exec-1'),
            $definition,
            Instant::of(1000)
        )->beginRunning();

        if ($status === 'succeeded') {
            return $execution->complete(ExecutionSuccess::create(), Instant::of(2000));
        }

        $failure = ExecutionStatus::Failed;
        return $execution->complete(
            ExecutionFailure::of($failure, ThrowableDescription::create(RuntimeException::class, 'boom', 1, null, '')),
            Instant::of(2000),
        );
    }
}
