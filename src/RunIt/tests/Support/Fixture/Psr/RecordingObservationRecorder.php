<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use ItPack\RunIt\Contract\Application\ObservationRecorderInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use Throwable;

/**
 * Test double for {@see ObservationRecorderInterface} that records every call and
 * returns a real observation so callers can keep using the returned artifact.
 */
final class RecordingObservationRecorder implements ObservationRecorderInterface
{
    /**
     * @var list<array{message: string, severity: ObservationSeverityInterface|null, attributes: AttributeCollectionInterface|null, throwable: Throwable|null, diagnosticCode: string|null}>
     */
    private array $recorded = [];

    public function record(
        string $message,
        ?ObservationSeverityInterface $severity = null,
        ?AttributeCollectionInterface $attributes = null,
        ?Throwable $throwable = null,
        ?string $diagnosticCode = null,
    ): ObservationInterface {
        $this->recorded[] = [
            'message' => $message,
            'severity' => $severity,
            'attributes' => $attributes,
            'throwable' => $throwable,
            'diagnosticCode' => $diagnosticCode,
        ];

        return $this->build($message, $severity ?? ObservationSeverity::Info, $attributes, $throwable, $diagnosticCode);
    }

    /**
     * @return list<array{message: string, severity: ObservationSeverityInterface|null, attributes: AttributeCollectionInterface|null, throwable: Throwable|null, diagnosticCode: string|null}>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }

    public function count(): int
    {
        return count($this->recorded);
    }

    private function build(
        string $message,
        ObservationSeverityInterface $severity,
        ?AttributeCollectionInterface $attributes,
        ?Throwable $throwable,
        ?string $diagnosticCode,
    ): ObservationInterface {
        $id = \ItPack\RunIt\Domain\Observation\ObservationId::fromString('obs-' . $this->count());
        $executionId = ExecutionId::fromString('recorder-execution');
        $description = $throwable instanceof Throwable ? $this->describe($throwable) : null;
        $arguments = [
            $id,
            $executionId,
            $message,
            null,
            $diagnosticCode,
            $attributes ?? AttributeCollection::empty(),
            $description,
            Instant::now(),
            ArtifactSequence::initial(),
        ];

        return match ($severity->label()) {
            'trace', 'debug' => Observation::debug(...$arguments),
            'info' => Observation::info(...$arguments),
            'notice' => Observation::notice(...$arguments),
            'warning' => Observation::warning(...$arguments),
            'error' => Observation::error(...$arguments),
            'critical' => Observation::critical(...$arguments),
            'alert' => Observation::alert(...$arguments),
            'emergency' => Observation::emergency(...$arguments),
            default => Observation::info(...$arguments),
        };
    }

    private function describe(Throwable $throwable): \ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface
    {
        return \ItPack\RunIt\Domain\Shared\ThrowableDescription::create(
            $throwable::class,
            $throwable->getMessage(),
            (int) $throwable->getCode(),
            null,
            null,
        );
    }
}
