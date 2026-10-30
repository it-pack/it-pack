<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Artifact;

use ItPack\AsyncIt\Application\Artifact\ArtifactVocabulary;
use ItPack\AsyncIt\Application\Artifact\SchedulerFailureArtifact;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Artifact\SchedulerFailureArtifact
 */
final class SchedulerFailureArtifactTest extends TestCase
{
    public function testNormalizeReturnsKindAndMessage(): void
    {
        $artifact = new SchedulerFailureArtifact('task blew up');

        $normalized = $artifact->normalize();

        self::assertSame(ArtifactVocabulary::KIND_SCHEDULER_FAILURE, $normalized['kind']);
        self::assertSame('task blew up', $normalized['message']);
    }

    public function testConstructsWithMessage(): void
    {
        $artifact = new SchedulerFailureArtifact('failure reason');

        self::assertSame('failure reason', $artifact->message);
    }
}
