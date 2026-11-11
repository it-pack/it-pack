<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;

/**
 * Composes the redaction and sampling publishers around a base publisher.
 *
 * Redaction is applied closest to the artifact so secrets are removed before
 * any sampling decision; sampling wraps the redacted stream so dropped artifacts
 * are never serialized or sent. The order is fixed by the assembly: redaction,
 * then sampling.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface PublisherStackFactoryInterface
{
    public function compose(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
    ): ArtifactPublisherInterface;
}
