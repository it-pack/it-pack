<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Vocabulary constants for the structured cancellation reason (closed set).
 *
 * The values are stable string labels; the {@see \ItPack\AsyncIt\Domain\CancellationReason}
 * enum binds to them. Each case carries a `@see` to its controlled individual
 * in the AsyncIt Scheduling Ontology.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationReason
 * @see https://w3id.org/itpack/asyncit#CancellationUserInitiated
 * @see https://w3id.org/itpack/asyncit#CancellationDeadlineExceeded
 * @see https://w3id.org/itpack/asyncit#CancellationParentCancelled
 * @see https://w3id.org/itpack/asyncit#CancellationResourceQuotaExceeded
 * @see https://w3id.org/itpack/asyncit#CancellationSystemShutdown
 */
interface CancellationReasonVocabulary
{
    public const string REASON_USER_INITIATED = 'user_initiated';

    public const string REASON_DEADLINE_EXCEEDED = 'deadline_exceeded';

    public const string REASON_PARENT_CANCELLED = 'parent_cancelled';

    public const string REASON_RESOURCE_QUOTA_EXCEEDED = 'resource_quota_exceeded';

    public const string REASON_SYSTEM_SHUTDOWN = 'system_shutdown';
}
