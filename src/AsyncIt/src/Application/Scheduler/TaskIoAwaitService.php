<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\AwaitPoint;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use Throwable;

/**
 * Owns IO-await state: which tasks are waiting on which poll handles.
 *
 * Extracted from {@see \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler}
 * during the Phase 4 SRP split. The service owns `$ioAwaiters` and
 * `$ioTaskHandles`; the scheduler calls it to register awaits, drain ready
 * handles, and release handles on terminal transition.
 *
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 * @see https://w3id.org/itpack/asyncit#Awaitable
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @api
 */
final class TaskIoAwaitService implements TaskIoAwaitServiceInterface
{
    /**
     * @var array<string, TaskRecordInterface> handle id => record awaiting it
     */
    private array $ioAwaiters = [];

    /**
     * @var array<string, string> task id => handle id it awaits on
     */
    private array $ioTaskHandles = [];

    public function __construct(
        private readonly PollerPortInterface $poller,
        private readonly TaskTimerServiceInterface $timerService,
    ) {
    }

    public function registerAwait(
        TaskRecordInterface $record,
        StreamPollHandleInterface $handle,
        AwaitPoint $point,
        DurationInterface|null $timeout = null,
    ): void {
        $handleId = $handle->id;
        $taskId = $record->task()->id()->value;

        $this->ioAwaiters[$handleId] = $record;
        $this->ioTaskHandles[$taskId] = $handleId;
        $record->addHandle($handle);

        if ($timeout instanceof DurationInterface) {
            $this->timerService->scheduleTimer($record, $timeout, $taskId, 'io');
        }
    }

    /**
     * Clears IO-await state for a task, used when its deadline timer expires
     * before the handle becomes ready.
     */
    public function clearIoAwaitForTask(string $taskId): void
    {
        $handleId = $this->ioTaskHandles[$taskId] ?? null;

        if ($handleId !== null) {
            unset($this->ioAwaiters[$handleId]);
        }

        unset($this->ioTaskHandles[$taskId]);
    }

    /**
     * Returns records whose poll handles became ready, cleaned up from the
     * await state.
     *
     * @return list<TaskRecordInterface>
     */
    public function drainReady(): array
    {
        $readyHandles = $this->poller->readyHandles();
        $ready = [];

        foreach ($readyHandles as $handle) {
            $handleId = $handle->id;

            if (!isset($this->ioAwaiters[$handleId])) {
                continue;
            }

            $record = $this->ioAwaiters[$handleId];
            $taskId = $record->task()->id()->value;

            unset($this->ioAwaiters[$handleId]);
            unset($this->ioTaskHandles[$taskId]);
            $this->timerService->clearForRecord($record);

            $ready[] = $record;
        }

        return $ready;
    }

    public function releaseForRecord(TaskRecordInterface $record): void
    {
        $taskId = $record->task()->id()->value;

        /** @var list<Throwable> $releaseErrors */
        $releaseErrors = [];

        foreach ($record->handles() as $handle) {
            try {
                $this->poller->release($handle);
            } catch (Throwable $e) {
                $releaseErrors[] = $e;
            }

            unset($this->ioAwaiters[$handle->id]);
        }

        unset($this->ioTaskHandles[$taskId]);

        $record->clearHandles();

        if ($releaseErrors !== []) {
            throw $releaseErrors[0];
        }
    }
}
