<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use ItPack\AsyncIt\Contract\Port\AwaitPoint;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * IO-await state: which tasks wait on which poll handles.
 *
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 * @see https://w3id.org/itpack/asyncit#Awaitable
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @api
 */
interface TaskIoAwaitServiceInterface
{
    public function registerAwait(TaskRecordInterface $record, StreamPollHandleInterface $handle, AwaitPoint $point, DurationInterface|null $timeout = null): void;

    public function clearIoAwaitForTask(string $taskId): void;

    /**
     * @return list<TaskRecordInterface>
     */
    public function drainReady(): array;

    public function releaseForRecord(TaskRecordInterface $record): void;
}
