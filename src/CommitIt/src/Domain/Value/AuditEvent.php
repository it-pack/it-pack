<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

use DateTimeImmutable;

/**
 * Immutable audit event recording a security-relevant repository action.
 *
 * Only non-sensitive identifiers (operation name, branch/tag name, level
 * and timestamp) are stored. Secret material is never captured here.
 *
 * @api
 */
final readonly class AuditEvent
{
    /**
     * @param array<string, string|int|bool|null> $context
     */
    public function __construct(
        public AuditLevel $level,
        public string $operation,
        public ?BranchName $branch = null,
        public ?TagName $tag = null,
        public array $context = [],
        public  ?DateTimeImmutable $timestamp = null,
    ) {
    }

    /**
     * @param array<string, string|int|bool|null> $context
     */
    public static function info(string $operation, array $context = [], ?BranchName $branch = null, ?TagName $tag = null): self
    {
        return new self(AuditLevel::Info, $operation, $branch, $tag, $context);
    }

    /**
     * @param array<string, string|int|bool|null> $context
     */
    public static function warning(string $operation, array $context = [], ?BranchName $branch = null, ?TagName $tag = null): self
    {
        return new self(AuditLevel::Warning, $operation, $branch, $tag, $context);
    }

    /**
     * @param array<string, string|int|bool|null> $context
     */
    public static function error(string $operation, array $context = [], ?BranchName $branch = null, ?TagName $tag = null): self
    {
        return new self(AuditLevel::Error, $operation, $branch, $tag, $context);
    }

    /**
     * @param array<string, string|int|bool|null> $context
     */
    public static function security(string $operation, array $context = [], ?BranchName $branch = null, ?TagName $tag = null): self
    {
        return new self(AuditLevel::Security, $operation, $branch, $tag, $context);
    }
}
