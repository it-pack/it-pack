<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * A single match from {@see \ItPack\CommitIt\Contract\SecretScanner::scan()}.
 *
 * The matched secret is redacted by default — only a classification
 * and byte offset are exposed so that callers cannot accidentally
 * log or persist the credential.
 *
 * @api
 */
final readonly class SecretMatch
{
    public function __construct(
        public SecretType $type,
        public int $offset,
        public int $length,
        public string $description,
    ) {
    }
}
