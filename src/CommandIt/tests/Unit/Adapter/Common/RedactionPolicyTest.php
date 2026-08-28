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

    public function testObjectWithToArrayIsReducedAndRedacted(): void
    {
        $policy = new RedactionPolicy();

        $payload = new class () {
            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return ['token' => 'secret-value', 'label' => 'ok'];
            }
        };

        $redacted = $policy->redact(['payload' => $payload]);
        $out = (array) $redacted['payload'];

        self::assertSame('***redacted***', $out['token'], 'nested sensitive key inside a reduced object is redacted');
        self::assertSame('ok', $out['label']);
    }

    public function testStringableObjectIsReducedToString(): void
    {
        $policy = new RedactionPolicy();

        $payload = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'user-123';
            }
        };

        $redacted = $policy->redact(['payload' => $payload]);

        self::assertSame('user-123', $redacted['payload']);
    }

    public function testArbitraryObjectBecomesMarker(): void
    {
        $policy = new RedactionPolicy();

        $redacted = $policy->redact(['payload' => new \stdClass()]);

        self::assertSame('***redacted***(object)', $redacted['payload'], 'objects without toArray/Stringable never pass through unchanged');
    }

    public function testDefaultMarkerMatchesInterfaceConstant(): void
    {
        $policy = new RedactionPolicy();

        $redacted = $policy->redact(['password' => 'hunter2']);

        self::assertSame(\ItPack\CommandIt\Contract\Port\RedactionPolicyInterface::DEFAULT_MARKER, $redacted['password']);
    }

    public function testSubstringModeRedactsPartialKey(): void
    {
        $policy = new RedactionPolicy();

        $redacted = $policy->redact(['monkey' => 'not-a-secret-key-but-matches-key']);

        self::assertSame('***redacted***', $redacted['monkey'], 'substring mode: "key" is a substring of "monkey"');
    }

    public function testExactModeDoesNotRedactPartialKey(): void
    {
        $policy = new RedactionPolicy(exactKeyMatch: true);

        $redacted = $policy->redact(['monkey' => 'value', 'key' => 'value']);

        self::assertSame('value', $redacted['monkey'], 'exact mode: "monkey" is not the whole key "key"');
        self::assertSame('***redacted***', $redacted['key'], 'exact mode: whole key "key" still matches');
    }
}
