<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;
use Throwable;

/**
 * Contract for recording observations bound to the ambient execution (and activity).
 *
 * The recorder maps a throwable through the configured policy, fills ownership,
 * timing, and sequence automatically. A returned observation is not proof of publication:
 * configured safety policies may drop it before publication.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
interface ObservationRecorderInterface
{
    public function record(
        string                        $message,
        ?ObservationSeverityInterface $severity = null,
        ?AttributeCollectionInterface $attributes = null,
        ?Throwable                    $throwable = null,
        ?string                       $diagnosticCode = null,
    ): ObservationInterface;
}
