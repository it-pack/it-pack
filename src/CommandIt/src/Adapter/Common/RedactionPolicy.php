<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Common;

use ItPack\CommandIt\Contract\Port\RedactionPolicyInterface;

/**
 * Default redaction policy.
 *
 * A context key is considered sensitive when its lower-cased name contains any
 * of the configured substrings; its value is then replaced wholesale by the
 * redaction marker (the value's type/size is not leaked). Non-sensitive values
 * are bounded: strings longer than {@see self::$maxValueLength} are truncated,
 * arrays are capped at {@see self::$maxArrayEntries} entries (the remainder is
 * summarized under a `_truncated` sentinel key, renamed `_truncated_N` when a
 * kept entry already claims that name), and nesting is limited to
 * {@see self::$maxDepth}.
 *
 * @api
 */
final class RedactionPolicy implements RedactionPolicyInterface
{
    /**
     * @param list<string> $sensitiveKeys case-insensitive substrings; a matching key's value is fully redacted
     * @param string       $marker         replacement for redacted values
     * @param int          $maxValueLength truncate string values longer than this
     * @param int          $maxArrayEntries keep at most this many entries in any array
     * @param int          $maxDepth       recursion depth limit for nested arrays
     */
    public function __construct(
        private array $sensitiveKeys = [
            'password', 'passwd', 'pwd', 'secret', 'token', 'api_key', 'apikey', 'api-key',
            'key', 'auth', 'authorization', 'credential', 'credentials', 'cvv', 'ssn', 'private',
        ],
        private string $marker = '***redacted***',
        private int $maxValueLength = 1024,
        private int $maxArrayEntries = 64,
        private int $maxDepth = 8,
    ) {
    }

    public function redact(array $context): array
    {
        $sensitive = \array_map(static fn (string $key): string => \strtolower($key), $this->sensitiveKeys);

        return $this->redactArray($context, 0, $sensitive);
    }

    /**
     * @param array<int|string, mixed> $context
     * @param array<int, string>        $sensitive
     *
     * @return array<string, mixed>
     */
    private function redactArray(array $context, int $depth, array $sensitive): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $out[(string) $key] = $this->isSensitive((string) $key, $sensitive)
                ? $this->marker
                : $this->redactValue($value, $depth + 1, $sensitive);
        }

        return $out;
    }

    /**
     * @param array<int, string> $sensitive
     *
     * @return mixed
     */
    private function redactValue(mixed $value, int $depth, array $sensitive): mixed
    {
        if (\is_array($value)) {
            if ($depth >= $this->maxDepth) {
                return $this->marker . '(depth)';
            }

            if (\count($value) > $this->maxArrayEntries) {
                $originalCount = \count($value);
                $value = \array_slice($value, 0, $this->maxArrayEntries, true);

                $sentinel = '_truncated';
                $suffix = 2;
                while (\array_key_exists($sentinel, $value)) {
                    $sentinel = '_truncated_' . $suffix;
                    ++$suffix;
                }

                $value[$sentinel] = \sprintf('%d entries omitted', $originalCount - $this->maxArrayEntries);
            }

            /** @var array<string, mixed> $value */
            return $this->redactArray($value, $depth, $sensitive);
        }

        if (\is_string($value) && \strlen($value) > $this->maxValueLength) {
            return \substr($value, 0, $this->maxValueLength) . \sprintf('…[%d chars]', \strlen($value));
        }

        return $value;
    }

    /**
     * @param array<int, string> $sensitive
     */
    private function isSensitive(string $key, array $sensitive): bool
    {
        $lower = \strtolower($key);

        foreach ($sensitive as $needle) {
            if (\str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }
}
