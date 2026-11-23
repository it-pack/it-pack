<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\PublisherStackFactoryInterface;

/**
 * Publisher stack factory spy that records composition calls and argument count.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class SpyPublisherStackFactory implements PublisherStackFactoryInterface
{
    public bool $called = false;

    public int $argumentCount = 0;

    public function __construct(private readonly ?ArtifactPublisherInterface $override = null)
    {
    }

    public function compose(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
    ): ArtifactPublisherInterface {
        $this->called = true;
        $this->argumentCount = func_num_args();

        return $this->override ?? $base;
    }
}
