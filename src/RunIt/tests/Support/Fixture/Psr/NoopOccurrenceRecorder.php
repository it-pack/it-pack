<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use ItPack\RunIt\Contract\Application\OccurrenceRecorderInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;

final class NoopOccurrenceRecorder implements OccurrenceRecorderInterface
{
    public function __construct(private readonly ExecutionIdInterface $executionId)
    {
    }

    public function record(
        \ItPack\RunIt\Contract\Domain\OccurrenceTypeInterface $type,
        string $source,
        mixed $data = null,
        ?string $subject = null,
        ?AttributeCollectionInterface $extensionAttributes = null,
        ?string $dataSchema = null,
        string $contentType = 'application/json',
    ): OccurrenceInterface {
        return \ItPack\RunIt\Domain\Occurrence\Occurrence::create(
            \ItPack\RunIt\Domain\Occurrence\OccurrenceId::fromString('noop-occurrence'),
            $this->executionId,
            $type,
            $source,
            \ItPack\RunIt\Domain\Shared\Instant::now(),
            $data,
        );
    }
}
