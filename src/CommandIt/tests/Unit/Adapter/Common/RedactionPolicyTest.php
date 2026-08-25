<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Common;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Common\RedactionPolicy;

/**
 * @covers \ItPack\CommandIt\Adapter\Common\RedactionPolicy
 */
final class RedactionPolicyTest extends Unit
{
    public function testSensitiveKeyIsFullyRedacted(): void
    {
        $policy = new RedactionPolicy();

        $redacted = $policy->redact(['password' => 'hunter2', 'user' => 'bob']);

        self::assertSame('***redacted***', $redacted['password']);
        self::assertSame('bob', $redacted['user']);
    }

    public function testPartialKeyMatchRedacts(): void
    {
        $policy = new RedactionPolicy();

        $redacted = $policy->redact(['api_key' => 'xyz', 'authorization' => 'Bearer x']);

        self::assertSame('***redacted***', $redacted['api_key']);
        self::assertSame('***redacted***', $redacted['authorization']);
    }

    public function testOversizedStringIsTruncated(): void
    {
        $policy = new RedactionPolicy(maxValueLength: 4);

        $redacted = $policy->redact(['note' => 'abcdefgh']);
        $note = $redacted['note'];

        self::assertIsString($note);
        self::assertStringContainsString('abcd', $note);
        self::assertStringNotContainsString('efgh', $note);
    }

    public function testArrayCardinalityIsBounded(): void
    {
        $policy = new RedactionPolicy(maxArrayEntries: 2);

        $redacted = $policy->redact(['items' => ['a', 'b', 'c', 'd']]);

        $items = (array) $redacted['items'];
        self::assertCount(3, $items);
        self::assertArrayHasKey('_truncated', $items);
        self::assertSame('2 entries omitted', $items['_truncated']);
    }

    public function testTruncationSummaryReportsOmittedCount(): void
    {
        $policy = new RedactionPolicy(maxArrayEntries: 64);

        $items = [];
        for ($i = 0; $i < 70; ++$i) {
            $items[] = $i;
        }

        $redacted = $policy->redact(['items' => $items]);
        $out = (array) $redacted['items'];

        self::assertCount(65, $out, '64 kept entries plus the truncation sentinel');
        self::assertSame('6 entries omitted', $out['_truncated']);
    }

    public function testTruncationSentinelCollidingWithKeptKeyIsRenamed(): void
    {
        $policy = new RedactionPolicy(maxArrayEntries: 2);

        $redacted = $policy->redact(['items' => ['_truncated' => 'real', 'a', 'b', 'c', 'd']]);
        $out = (array) $redacted['items'];

        self::assertSame('real', $out['_truncated'], 'the kept entry is never overwritten');
        self::assertSame('3 entries omitted', $out['_truncated_2']);
    }

    public function testNestedSensitiveKeyIsRedacted(): void
    {
        $policy = new RedactionPolicy();

        $redacted = $policy->redact(['meta' => ['token' => 'secret-value']]);
        $meta = (array) $redacted['meta'];

        self::assertSame('***redacted***', $meta['token']);
    }
}
