<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use RuntimeException;

/**
 * Result of {@see ManifestVerifier::verify()}: what matched, what diverged, and
 * what could not be read. Comparison uses {@see Contract\HashInterface::equals()} (constant
 * time), so mismatches never leak timing.
 */
final readonly class VerificationReport
{
    /**
     * @param array<string, Contract\HashInterface> $matched
     * @param list<HashMismatch> $mismatched
     * @param list<string> $missing
     */
    public function __construct(
        private array $matched,
        private array $mismatched,
        private array $missing,
    ) {
    }

    /**
     * @return array<string, Contract\HashInterface>
     */
    public function matched(): array
    {
        return $this->matched;
    }

    /**
     * @return list<HashMismatch>
     */
    public function mismatched(): array
    {
        return $this->mismatched;
    }

    /**
     * @return list<string>
     */
    public function missing(): array
    {
        return $this->missing;
    }

    public function allMatched(): bool
    {
        return $this->mismatched === [] && $this->missing === [];
    }

    /**
     * Throw a {@see RuntimeException} if any entry failed to verify, summarising
     * the mismatch/missing counts. Convenience for callers that want fail-closed.
     */
    public function throwOnFailure(): void
    {
        if ($this->allMatched()) {
            return;
        }

        $parts = [];
        if ($this->mismatched !== []) {
            $parts[] = count($this->mismatched) . ' mismatched';
        }
        if ($this->missing !== []) {
            $parts[] = count($this->missing) . ' missing';
        }

        throw new RuntimeException('Manifest verification failed: ' . implode(', ', $parts));
    }
}
