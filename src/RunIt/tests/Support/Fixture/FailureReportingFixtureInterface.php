<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use Throwable;

interface FailureReportingFixtureInterface
{
    public function report(Throwable $failure, ?ExecutionArtifactInterface $artifact = null): void;
}
