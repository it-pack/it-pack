<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\RedactorVerifier;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use RuntimeException;

/**
 * @group conformance
 */
final class RedactorVerifierTest extends Unit
{
    public function testPortReportsRedactorInterface(): void
    {
        self::assertSame(RedactorInterface::class, new RedactorVerifier()->port());
    }

    public function testConformantRedactorPasses(): void
    {
        $redactor = new class () implements RedactorInterface {
            public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
            {
                return $artifact;
            }
        };

        $result = new RedactorVerifier()->verify($redactor, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertSame('redact.secret.leaks', $result->errors()[0]->id);
    }

    public function testRedactSafeThrowIsRecorded(): void
    {
        $redactor = new class () implements RedactorInterface {
            public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
            {
                throw new RuntimeException('redact safe broken');
            }
        };

        $result = new RedactorVerifier()->verify($redactor, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertSame('redact.safe.throws', $result->errors()[0]->id);
    }

    public function testRedactSecretThrowIsRecorded(): void
    {
        $redactor = new class () implements RedactorInterface {
            public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
            {
                if ($artifact instanceof Observation && $artifact->message() === 'Connected to upstream') {
                    throw new RuntimeException('redact secret broken');
                }

                return $artifact;
            }
        };

        $result = new RedactorVerifier()->verify($redactor, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertSame('redact.secret.throws', $result->errors()[0]->id);
    }

    public function testRedactSafeAltersIsRecorded(): void
    {
        $replacement = Observation::info(
            ObservationId::fromString('obs-other'),
            ExecutionId::fromString('exec-0001'),
            'replacement',
            attributes: AttributeCollection::fromArray([]),
            instant: Instant::of(1_700_000_000),
        );

        $redactor = new readonly class ($replacement) implements RedactorInterface {
            public function __construct(private ExecutionArtifactInterface $replacement)
            {
            }

            public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
            {
                return $this->replacement;
            }
        };

        $result = new RedactorVerifier()->verify($redactor, new RunItPortFixture());

        self::assertFalse($result->isConformant());
        self::assertSame('redact.safe.alters', $result->errors()[0]->id);
    }
}
