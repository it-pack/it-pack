<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;

/**
 * Thrown when an artifact is published while publication is already active.
 *
 * This detects recursive publication on one execution path: for example, a health
 * reporter or a fallback that would publish through the same guarded pipeline, or an
 * exporter that emits an observation about its own export. The guarded publisher
 * catches this and routes the artifact to the internal health reporter instead.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RecursivePublicationException extends \RuntimeException implements RunItExceptionInterface
{
}
