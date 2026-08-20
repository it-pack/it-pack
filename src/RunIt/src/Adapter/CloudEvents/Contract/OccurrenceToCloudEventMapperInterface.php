<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\CloudEvents\Contract;

use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;

/**
 * Maps a RunIt occurrence to a CloudEvent.
 *
 * Occurrences are immutable RunIt facts; CloudEvents are the wire representation.
 * The mapper preserves the occurrence identity, type, source, subject, time,
 * data, schema, and content type, and carries RunIt correlation (execution,
 * activity, sequence, correlation id) as CloudEvents extension attributes.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 * @see https://w3id.org/itpack/runit#CloudEvent
 */
interface OccurrenceToCloudEventMapperInterface
{
    public function map(
        OccurrenceInterface $occurrence,
        ?CloudEventMappingPolicy $policy = null,
    ): CloudEvent;
}
