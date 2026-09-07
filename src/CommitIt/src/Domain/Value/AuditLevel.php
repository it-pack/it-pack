<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Severity level for an {@see AuditEvent}.
 *
 * @api
 */
enum AuditLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Security = 'security';
}
