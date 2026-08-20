<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Event;

use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceTypeInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\Instant;

/**
 * Configurable default mapper from PSR-14 event objects to RunIt occurrences.
 *
 * The mapper binds every mapped occurrence to a configured execution identity,
 * mints a fresh occurrence identifier, and derives the payload from the event via
 * a configurable data extractor (by default the event's public properties). The
 * occurrence type defaults to a configured value and may be overridden per call.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
final class DefaultPsrEventToOccurrenceMapper implements PsrEventToOccurrenceMapperInterface
{
    /**
     * @param mixed $dataExtractor optional callable(object): mixed that extracts the occurrence payload
     */
    public function __construct(
        private readonly OccurrenceTypeInterface $defaultType,
        private readonly ExecutionIdInterface $executionId,
        private readonly IdentifierGeneratorInterface $idGenerator,
        private readonly string $source = 'psr.event',
        private readonly ?Instant $time = null,
        private readonly mixed $dataExtractor = null,
    ) {
    }

    public function map(object $event, ?string $type = null): OccurrenceInterface
    {
        $data = $this->extractData($event);

        return Occurrence::create(
            OccurrenceId::fromString($this->idGenerator->generate()),
            $this->executionId,
            $type !== null ? OccurrenceType::fromString($type) : $this->defaultType,
            $this->source,
            $this->time ?? Instant::now(),
            $data,
        );
    }

    private function extractData(object $event): mixed
    {
        if (is_callable($extractor = $this->dataExtractor)) {
            return $extractor($event);
        }

        $data = [];

        foreach (get_object_vars($event) as $key => $value) {
            if (is_object($value) || is_resource($value)) {
                continue;
            }

            $data[$key] = $value;
        }

        return $data === [] ? null : $data;
    }
}
