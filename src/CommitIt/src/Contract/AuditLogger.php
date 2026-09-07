<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Value\AuditEvent;

/**
 * Records security-relevant repository events for audit purposes.
 *
 * Implementations may write to a file, syslog, PSR-3 logger, or a remote
 * collector. A {@see NullAuditLogger} is always available for consumers
 * that want zero-overhead auditing.
 *
 * @api
 */
interface AuditLogger
{
    public function log(AuditEvent $event): void;
}
