<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\TaskIdInterface;

/**
 * Deduplication record surface mapping an idempotency key to a persisted task
 * id.
 *
 * Backs {@see DeduplicationRecord} so deduplication remains mockable and
 * interface-driven.
 *
 * @see https://w3id.org/itpack/asyncit#DeduplicationRecord
 * @api
 */
interface DeduplicationRecordInterface
{
    /**
     * Returns the task id this record maps to.
     */
    public function taskId(): TaskIdInterface;

    /**
     * Returns the idempotency key.
     */
    public function idempotencyKey(): string;

    /**
     * Returns the monotonic timestamp when the record was created.
     */
    public function recordedAt(): int;
}
