<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Common;

use ItPack\CommandIt\Contract\Port\RedactionPolicyInterface;

/**
 * Default redaction policy.
 *
 * Two-stage masking model:
 *
 *  1. Classification mask {@see \ItPack\CommandIt\Contract\Domain\CommandContextInterface::REDACTED} (`***`)
 *     is applied unconditionally by `CommandContext::redacted()` for any `Secret`
 *     entry, before the value ever reaches a reporter.
 *  2. Policy mask {@see \ItPack\CommandIt\Contract\Port\RedactionPolicyInterface::DEFAULT_MARKER}
 *     (`***redacted***`) is applied by this policy to whole values whose key is
 *     sensitive, used by `RedactingExecutionReporter`.
 *
 * A context key is considered sensitive when its lower-cased name matches per the
 * configured mode: by default a substring (case-insensitive; `key` also matches
 * `monkey`), or, when {@see self::__construct()}'s `$exactKeyMatch` is true, a
 * whole-key match. A sensitive value is replaced wholesale by the marker (its
 * type/size is not leaked). Objects are reduced to a primitive form before
 * redaction: `toArray()`-bearing objects are reduced through that method,
 * {@see \Stringable} objects become their string form, and anything else becomes
 * `marker . '(object)'`. Non-sensitive values are bounded: strings longer than
 * {@see self::$maxValueLength} are truncated, arrays are capped at
 * {@see self::$maxArrayEntries} entries (the remainder is summarized under a
 * `_truncated` sentinel key, renamed `_truncated_N` when a kept entry already
 * claims that name), and nesting is limited to {@see self::$maxDepth}.
 *
 * @api
 */
final readonly class RedactionPolicy implements RedactionPolicyInterface
{
    /**
     * @param list<string> $sensitiveKeys case-insensitive key fragments (substring) or whole keys (exact, see $exactKeyMatch)
     * @param string       $marker         replacement for redacted values
     * @param int          $maxValueLength truncate string values longer than this
     * @param int          $maxArrayEntries keep at most this many entries in any array
     * @param int          $maxDepth       recursion depth limit for nested arrays
     * @param bool         $exactKeyMatch  when true, sensitivity compares whole lowercased keys (no substring); default (false) keeps the historical substring behavior
     */
    public function __construct(
        private array $sensitiveKeys = [
            'password', 'passwd', 'pwd', 'secret', 'token', 'api_key', 'apikey', 'api-key',
            'key', 'auth', 'authorization', 'credential', 'credentials', 'cvv', 'ssn', 'private',
        ],
        private string $marker = RedactionPolicyInterface::DEFAULT_MARKER,
        private int $maxValueLength = 1024,
        private int $maxArrayEntries = 64,
        private int $maxDepth = 8,
        private bool $exactKeyMatch = false,
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

        if (\is_object($value)) {
            if (\method_exists($value, 'toArray')) {
                /** @var mixed $asArray */
                $asArray = $value->toArray();

                if (\is_array($asArray)) {
                    return $this->redactArray($asArray, $depth, $sensitive);
                }
            }

            if ($value instanceof \Stringable) {
                return (string) $value;
            }

            return $this->marker . '(object)';
        }

        return $value;
    }

    /**
     * @param array<int, string> $sensitive lowercased fragments (substring) or keys (exact)
     */
    private function isSensitive(string $key, array $sensitive): bool
    {
        $lower = \strtolower($key);

        if ($this->exactKeyMatch) {
            return \in_array($lower, $sensitive, true);
        }

        foreach ($sensitive as $needle) {
            if (\str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }
}
