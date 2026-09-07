<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Service;

use ItPack\CommitIt\Contract\AuditLogger;
use ItPack\CommitIt\Domain\Value\AuditEvent;

/**
 * No-op {@see AuditLogger} for consumers who do not require audit logging.
 *
 * @api
 */
final readonly class NullAuditLogger implements AuditLogger
{
    public function log(AuditEvent $event): void
    {
    }
}
