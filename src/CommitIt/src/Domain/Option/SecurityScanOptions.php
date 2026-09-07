<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Option;

/**
 * Configuration for {@see DefaultSecretScanner}.
 *
 * @api
 */
final readonly class SecurityScanOptions
{
    private function __construct(
        public ?int $maxMatchLength,
        public bool $scanCommitMessages,
        public bool $scanFileContents,
        public bool $abortOnMatch,
        public bool $redactInLogs,
    ) {
    }

    public static function default(): self
    {
        return new self(
            maxMatchLength: 4096,
            scanCommitMessages: true,
            scanFileContents: true,
            abortOnMatch: true,
            redactInLogs: true,
        );
    }

    public function withMaxMatchLength(?int $max): self
    {
        return new self($max, $this->scanCommitMessages, $this->scanFileContents, $this->abortOnMatch, $this->redactInLogs);
    }

    public function shouldScanMessages(bool $scan = true): self
    {
        return new self($this->maxMatchLength, $scan, $this->scanFileContents, $this->abortOnMatch, $this->redactInLogs);
    }

    public function shouldScanContents(bool $scan = true): self
    {
        return new self($this->maxMatchLength, $this->scanCommitMessages, $scan, $this->abortOnMatch, $this->redactInLogs);
    }

    public function abortOnMatch(bool $abort = true): self
    {
        return new self($this->maxMatchLength, $this->scanCommitMessages, $this->scanFileContents, $abort, $this->redactInLogs);
    }

    public function redactInLogs(bool $redact = true): self
    {
        return new self($this->maxMatchLength, $this->scanCommitMessages, $this->scanFileContents, $this->abortOnMatch, $redact);
    }
}
