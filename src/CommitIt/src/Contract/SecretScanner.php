<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Value\SecretMatch;

/**
 * Scans content for secrets, credentials, and sensitive data.
 *
 * Implementations must be pure: no filesystem access, no network calls.
 *
 * @api
 */
interface SecretScanner
{
    /**
     * @param string $content Text (commit message, file content) to scan.
     * @return list<SecretMatch> All matches found, in order of appearance.
     */
    public function scan(string $content): array;
}
