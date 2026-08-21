<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use ItPack\DeriveIt\Generator\DeriveDiff;

/**
 * Pinning tests for {@see DeriveDiff} so the compact unified diff keeps its
 * mutation score (the diff is the only non-trivial new code in Step 10.10).
 */
class DeriveDiffTest extends \Codeception\Test\Unit
{
    public function testNoDriftForIdenticalSources(): void
    {
        $diff = DeriveDiff::compute("A\nB\nC", "A\nB\nC");

        self::assertFalse($diff->drift);
        self::assertSame('', $diff->diff);
        self::assertSame("A\nB\nC", $diff->existing);
        self::assertSame("A\nB\nC", $diff->generated);
    }

    public function testAppendAtEnd(): void
    {
        $diff = DeriveDiff::compute("A\nB", "A\nB\nC");

        self::assertTrue($diff->drift);
        self::assertSame("@@ -3,0 +3,1 @@\n+ C\n", $diff->diff);
    }

    public function testRemoveAtEnd(): void
    {
        $diff = DeriveDiff::compute("A\nB\nC", "A\nB");

        self::assertTrue($diff->drift);
        self::assertSame("@@ -3,1 +3,0 @@\n- C\n", $diff->diff);
    }

    public function testChangeInMiddle(): void
    {
        $diff = DeriveDiff::compute("A\nB\nC", "A\nX\nC");

        self::assertTrue($diff->drift);
        self::assertSame("@@ -2,1 +2,1 @@\n- B\n+ X\n", $diff->diff);
    }

    public function testInsertInMiddle(): void
    {
        $diff = DeriveDiff::compute("A\nB\nC", "A\nX\nB\nC");

        self::assertTrue($diff->drift);
        self::assertSame("@@ -2,0 +2,1 @@\n+ X\n", $diff->diff);
    }

    public function testReplaceMultipleLines(): void
    {
        $diff = DeriveDiff::compute("A\nB\nC\nD", "A\nX\nY\nD");

        self::assertTrue($diff->drift);
        self::assertSame("@@ -2,2 +2,2 @@\n- B\n- C\n+ X\n+ Y\n", $diff->diff);
    }

    public function testCompletelyDifferent(): void
    {
        $diff = DeriveDiff::compute("A\nB", "X\nY");

        self::assertTrue($diff->drift);
        self::assertSame("@@ -1,2 +1,2 @@\n- A\n- B\n+ X\n+ Y\n", $diff->diff);
    }

    public function testPrepend(): void
    {
        $diff = DeriveDiff::compute("B\nC", "A\nB\nC");

        self::assertTrue($diff->drift);
        self::assertSame("@@ -1,0 +1,1 @@\n+ A\n", $diff->diff);
    }
}
