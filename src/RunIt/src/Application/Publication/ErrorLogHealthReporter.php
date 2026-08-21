<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;

/**
 * Health reporter that writes failures to the PHP error log.
 *
 * It is the safe default for environments without an external health sink: the
 * message includes the artifact type and id so operators can correlate the
 * dropped or recursive publication without it ever calling the guarded publisher.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ErrorLogHealthReporter implements InternalHealthReporterInterface
{
    public function report(\Throwable $error, ExecutionArtifactInterface $artifact): void
    {
        $id = $artifact->normalize()[ArtifactField::ID] ?? 'unknown';
        $idText = is_string($id) ? $id : '';

        error_log(sprintf(
            '[runit] publication health: %s in %s:%d while publishing %s %s',
            $error->getMessage(),
            $error->getFile(),
            $error->getLine(),
            $artifact::class,
            $idText,
        ));
    }
}
