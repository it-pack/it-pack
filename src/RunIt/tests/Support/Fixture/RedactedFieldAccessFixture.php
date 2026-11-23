<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Adapter\Common\Behavior\RedactedFieldAccess;

/**
 * Test fixture exposing {@see RedactedFieldAccess} trait methods for direct testing.
 *
 * The trait's accessors are private; this fixture wraps them with public methods
 * so the trait can be tested in isolation from CloudEventPublisher and
 * DefaultOccurrenceLogMapper. Inside this class, `$this->redactedString(...)`
 * resolves to the trait's private method.
 */
final class RedactedFieldAccessFixture
{
    use RedactedFieldAccess;

    /**
     * @param array<string, mixed> $normalized
     */
    public function redactedStringValue(array $normalized, string $key, string $default): string
    {
        return $this->redactedString($normalized, $key, $default);
    }

    /**
     * @param array<string, mixed> $normalized
     */
    public function redactedStringValueOrNull(array $normalized, string $key): ?string
    {
        return $this->redactedStringOrNull($normalized, $key);
    }

    /**
     * @param array<string, mixed> $normalized
     */
    public function redactedIntValueOrNull(array $normalized, string $key): ?int
    {
        return $this->redactedIntOrNull($normalized, $key);
    }
}
