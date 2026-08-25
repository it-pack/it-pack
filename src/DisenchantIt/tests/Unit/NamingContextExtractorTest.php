<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\NamingContextExtractor;
use ItPack\DisenchantIt\ValueObject\NamingContextInterface;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;

final class NamingContextExtractorTest extends Unit
{
    private function extractFrom(string $code): NamingContextInterface
    {
        $parser = new ParserFactory()->createForNewestSupportedVersion();
        $ast = $parser->parse($code);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ParentConnectingVisitor());
        $traverser->traverse($ast);

        /** @var list<LNumber> $numbers */
        $numbers = new NodeFinder()->findInstanceOf($ast, LNumber::class);
        $node = $numbers[0];

        return new NamingContextExtractor()->extract($node, $node->getStartLine(), $code);
    }

    public function testVariableAssignmentWithUnitSuffix(): void
    {
        $ctx = $this->extractFrom('<?php
$timeoutMs = 100;');

        $this->assertSame('timeoutMs', $ctx->variableName);
        $this->assertSame('ms', $ctx->unit);
        $this->assertNull($ctx->comment);
    }

    public function testVariableAssignmentWithoutUnit(): void
    {
        $ctx = $this->extractFrom('<?php
$count = 5;');

        $this->assertSame('count', $ctx->variableName);
        $this->assertNull($ctx->unit);
    }

    public function testTypedPropertyDefault(): void
    {
        $ctx = $this->extractFrom('<?php
class C
{
    private int $ttlSeconds = 30;
}');

        $this->assertSame('ttlSeconds', $ctx->variableName);
        $this->assertSame('seconds', $ctx->unit);
    }

    public function testParameterDefault(): void
    {
        $ctx = $this->extractFrom('<?php
class C
{
    public function f(int $limitBytes = 5): void {}
}');

        $this->assertSame('limitBytes', $ctx->variableName);
        $this->assertSame('bytes', $ctx->unit);
    }

    public function testArrayValueWithStringKey(): void
    {
        $ctx = $this->extractFrom('<?php
$arr = ["key" => 1];');

        $this->assertSame('key', $ctx->variableName);
        $this->assertNull($ctx->unit);
    }

    public function testArrayDimFetchAssignment(): void
    {
        $ctx = $this->extractFrom('<?php
$config["delayMs"] = 5;');

        $this->assertSame('config', $ctx->variableName);
        $this->assertNull($ctx->unit);
    }

    public function testPropertyFetchAssignment(): void
    {
        $ctx = $this->extractFrom('<?php
$obj->delayMs = 9;');

        $this->assertSame('delayMs', $ctx->variableName);
        $this->assertSame('ms', $ctx->unit);
    }

    public function testPrecedingCommentIsCaptured(): void
    {
        $ctx = $this->extractFrom('<?php
// some hint
$bar = 200;');

        $this->assertSame('bar', $ctx->variableName);
        $this->assertSame('some hint', $ctx->comment);
    }

    public function testCodeLineAboveYieldsNoComment(): void
    {
        $ctx = $this->extractFrom('<?php
function g(): void {}
$bar = 200;');

        $this->assertSame('bar', $ctx->variableName);
        $this->assertNull($ctx->comment);
    }

    public function testEmptyCommentYieldsNull(): void
    {
        $ctx = $this->extractFrom('<?php
//
$bar = 200;');

        $this->assertSame('bar', $ctx->variableName);
        $this->assertNull($ctx->comment);
    }

    public function testBlankLineSkippedBeforeComment(): void
    {
        $ctx = $this->extractFrom('<?php
$x = "a";

// hint after blank
$bar = 200;');

        $this->assertSame('bar', $ctx->variableName);
        $this->assertSame('hint after blank', $ctx->comment);
    }
}
