<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use ItPack\RunIt\Contract\Application\OccurrenceRecorderInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceTypeInterface;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Shared\Instant;

final class NoopOccurrenceRecorder implements OccurrenceRecorderInterface
{
    public function __construct(private readonly ExecutionIdInterface $executionId)
    {
    }

    public function record(
        OccurrenceTypeInterface $type,
        string $source,
        mixed $data = null,
        ?string $subject = null,
        ?AttributeCollectionInterface $extensionAttributes = null,
        ?string $dataSchema = null,
        string $contentType = 'application/json',
    ): OccurrenceInterface {
        return Occurrence::create(
            OccurrenceId::fromString('noop-occurrence'),
            $this->executionId,
            $type,
            $source,
            Instant::now(),
            $data,
        );
    }
}
