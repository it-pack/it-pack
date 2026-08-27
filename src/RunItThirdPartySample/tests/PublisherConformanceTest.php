<?php

declare(strict_types=1);

namespace ItPack\RunItThirdPartySample\Tests;

use ItPack\RunIt\Conformance\Fixture\RunItPortFixture;
use ItPack\RunIt\Conformance\Verifier\ArtifactPublisherVerifier;
use ItPack\RunItThirdPartySample\Publisher\BufferedFilePublisher;
use ItPack\RunItThirdPartySample\Publisher\StreamLinePublisher;
use PHPUnit\Framework\TestCase;

/**
 * Drives the shipped {@see ArtifactPublisherVerifier} against the sample publishers.
 *
 * No test-framework base class is extended beyond PHPUnit's TestCase, and the only
 * RunIt surface used is the public conformance toolkit — exactly what a third party
 * would rely on.
 */
final class PublisherConformanceTest extends TestCase
{
    public function testStreamLinePublisherPassesArtifactPublisherConformance(): void
    {
        $stream = fopen('php://temp', 'r+');

        $publisher = new StreamLinePublisher($stream);
        $result = (new ArtifactPublisherVerifier())->verify($publisher, new RunItPortFixture());

        self::assertTrue($result->isConformant(), $result->summary());

        rewind($stream);
        $output = stream_get_contents($stream);
        self::assertIsString($output);
        self::assertNotEmpty($output);
    }

    public function testBufferedFilePublisherPassesArtifactPublisherConformance(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'runit-sample-');
        self::assertIsString($path);

        $publisher = new BufferedFilePublisher($path);
        $result = (new ArtifactPublisherVerifier())->verify($publisher, new RunItPortFixture());

        self::assertTrue($result->isConformant(), $result->summary());

        // The verifier publishes every artifact twice (4 kinds) and flushes on close.
        $raw = file_get_contents($path);
        self::assertIsString($raw);
        $lines = explode("\n", rtrim($raw, "\n"));
        self::assertCount(8, $lines);

        unlink($path);
    }
}
