<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskResultInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use Throwable;

/**
 * Typed terminal outcome of a task.
 *
 * Encapsulates either a success value or a terminal throwable, together with
 * the final {@see TaskStatus} and the monotonic elapsed duration. Immutable
 * and readonly — constructed once via {@see self::success()} or
 * {@see self::failure()}.
 *
 * Generics are PHPDoc-only (`@template T`) — PHP 8.4 does not enforce generic
 * type relationships at runtime. Consumers should treat `value()` as the
 * success-type value only when `isSuccess()` is true.
 *
 * @see https://w3id.org/itpack/asyncit#TaskResult
 * @see https://w3id.org/itpack/runit#Observation
 */
final readonly class TaskResult implements TaskResultInterface
{
    private function __construct(
        private TaskStatus $status,
        private DurationInterface $duration,
        private mixed $value = null,
        private ?Throwable $error = null,
    ) {
        if ($error instanceof Throwable) {
            if (!$status->isTerminal()) {
                throw new InvalidArgumentException('Failure results require a terminal status.');
            }
        }

        if (!$error instanceof Throwable && $status !== TaskStatus::Succeeded) {
            throw new InvalidArgumentException('Success results require TaskStatus::Succeeded.');
        }
    }

    /**
     * Creates a successful result.
     */
    public static function success(mixed $value, TaskStatus $status, DurationInterface $duration): self
    {
        return new self($status, $duration, $value);
    }

    /**
     * Creates a failed result.
     */
    public static function failure(Throwable $error, TaskStatus $status, DurationInterface $duration): self
    {
        return new self($status, $duration, error: $error);
    }

    public function isSuccess(): bool
    {
        return $this->status === TaskStatus::Succeeded;
    }

    public function isFailure(): bool
    {
        return in_array($this->status, [TaskStatus::Failed, TaskStatus::Cancelled, TaskStatus::TimedOut], strict: true);
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function error(): ?Throwable
    {
        return $this->error;
    }

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function duration(): DurationInterface
    {
        return $this->duration;
    }
}
