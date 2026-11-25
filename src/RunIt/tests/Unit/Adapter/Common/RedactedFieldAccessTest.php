<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Common;

use Codeception\Test\Unit;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Support\Fixture\RedactedFieldAccessFixture;

/**
 * Tests for the shared RedactedFieldAccess trait, which centralizes
 * type-checked redacted field extraction used by CloudEventPublisher and
 * DefaultOccurrenceLogMapper.
 */
#[CoversNothing]
final class RedactedFieldAccessTest extends Unit
{
    private RedactedFieldAccessFixture $fixture;

    protected function _before(): void
    {
        $this->fixture = new RedactedFieldAccessFixture();
    }

    public function testRedactedStringReturnsValueWhenPresent(): void
    {
        self::assertSame(
            'exec-1',
            $this->fixture->redactedStringValue(['id' => 'exec-1'], 'id', 'redacted')
        );
    }

    public function testRedactedStringReturnsDefaultWhenMissing(): void
    {
        self::assertSame('redacted', $this->fixture->redactedStringValue([], 'id', 'redacted'));
    }

    public function testRedactedStringReturnsDefaultWhenNotString(): void
    {
        self::assertSame('redacted', $this->fixture->redactedStringValue(['id' => 42], 'id', 'redacted'));
    }

    public function testRedactedStringOrNullReturnsValueWhenPresent(): void
    {
        self::assertSame('exec-1', $this->fixture->redactedStringValueOrNull(['id' => 'exec-1'], 'id'));
    }

    public function testRedactedStringOrNullReturnsNullWhenMissing(): void
    {
        self::assertNull($this->fixture->redactedStringValueOrNull([], 'id'));
    }

    public function testRedactedStringOrNullReturnsNullWhenNotString(): void
    {
        self::assertNull($this->fixture->redactedStringValueOrNull(['id' => 42], 'id'));
    }

    public function testRedactedIntOrNullReturnsValueWhenPresent(): void
    {
        self::assertSame(1_600, $this->fixture->redactedIntValueOrNull(['time' => 1_600], 'time'));
    }

    public function testRedactedIntOrNullReturnsNullWhenMissing(): void
    {
        self::assertNull($this->fixture->redactedIntValueOrNull([], 'time'));
    }

    public function testRedactedIntOrNullReturnsNullWhenNotInt(): void
    {
        self::assertNull($this->fixture->redactedIntValueOrNull(['time' => '1600'], 'time'));
        self::assertNull($this->fixture->redactedIntValueOrNull(['time' => 1.5], 'time'));
    }
}
