<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Closed set of reasons a {@see \ItPack\AsyncIt\Contract\CancellationScopeInterface} may be cancelled.
 *
 * The reason is immutable once set: a scope records the first reason it
 * receives and subsequent cancel() calls do not overwrite it.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationReason
 * @see https://w3id.org/itpack/asyncit#CancellationUserInitiated
 * @see https://w3id.org/itpack/asyncit#CancellationDeadlineExceeded
 * @see https://w3id.org/itpack/asyncit#CancellationParentCancelled
 * @see https://w3id.org/itpack/asyncit#CancellationResourceQuotaExceeded
 * @see https://w3id.org/itpack/asyncit#CancellationSystemShutdown
 */
enum CancellationReason: string
{
    case UserInitiated = CancellationReasonVocabulary::REASON_USER_INITIATED;

    case DeadlineExceeded = CancellationReasonVocabulary::REASON_DEADLINE_EXCEEDED;

    case ParentCancelled = CancellationReasonVocabulary::REASON_PARENT_CANCELLED;

    case ResourceQuotaExceeded = CancellationReasonVocabulary::REASON_RESOURCE_QUOTA_EXCEEDED;

    case SystemShutdown = CancellationReasonVocabulary::REASON_SYSTEM_SHUTDOWN;
}
