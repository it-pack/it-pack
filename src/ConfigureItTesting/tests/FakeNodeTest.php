<?php

declare(strict_types=1);

namespace ItPack\ConfigureItTesting\Tests;

use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\SourceKind;
use ItPack\ConfigureItTesting\Node\FakeNode;
use PHPUnit\Framework\TestCase;

final class FakeNodeTest extends TestCase
{
    public function testCreateUsesSensibleDefaults(): void
    {
        $node = FakeNode::create('app.name', 'it', 'base', 10);

        self::assertSame('it', $node->value());
        self::assertSame('base', $node->source());
        self::assertSame(10, $node->priority());
        self::assertFalse($node->provenance()->isSensitive());
        self::assertSame('programmatic', $node->provenance()->sourceKind());
    }

    public function testSensitiveMarksProvenance(): void
    {
        $node = FakeNode::sensitive('db.password', 'secret');

        self::assertTrue($node->provenance()->isSensitive());
        self::assertSame('vault', $node->provenance()->sourceKind());
    }

    public function testOverrideRecordsShadowedSource(): void
    {
        $node = FakeNode::override('app.debug', false, 'local', 20, 'base.json', SourceKind::File);

        $provenance = $node->provenance();
        self::assertTrue($provenance->overridden());
        self::assertSame('base.json', $provenance->overriddenSource());
        self::assertSame('file', $provenance->sourceKind());
        self::assertSame(20, $provenance->priority());
    }

    public function testFromSetsAdapterAndFormat(): void
    {
        $node = FakeNode::from('cache.ttl', 60, 'redis.php', 5, SourceKind::File, 'yaml-loader', Format::YAML);

        $provenance = $node->provenance();
        self::assertSame('yaml-loader', $provenance->adapter());
        self::assertSame(Format::YAML, $provenance->format());
        self::assertSame('file', $provenance->sourceKind());
    }
}
