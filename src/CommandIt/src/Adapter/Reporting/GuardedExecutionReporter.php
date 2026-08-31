<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Reporting;

use Fiber;
use ItPack\CommandIt\Contract\Port\ExecutionReporterInterface;
use WeakMap;

/**
 * Wraps a publication delegate with a recursion guard and a separate health path.
 *
 * Two failure modes of external publication are contained here:
 *
 *  - **Self-observation loop:** if publishing an entry causes the (same) sink to
 *    report back through this reporter, the nested call is detected by a depth
 *    counter and routed to the {@see $health} reporter instead of re-entering the
 *    delegate — breaking the loop without swallowing the original signal.
 *  - **Publication failure:** if the delegate throws, the error is reported to the
 *    {@see $health} reporter and never rethrown into the business flow.
 *
 * The depth counter is isolated per Fiber (F-12): a {@see WeakMap} keyed by the
 * currently executing {@see Fiber} tracks each Fiber's nesting independently, with
 * a plain field as the fallback for synchronous (main-scope) code. This prevents a
 * delegate that synchronously drives a *different* Fiber from inflating the calling
 * Fiber's depth and being false-routed to health.
 *
 * The health reporter is a distinct instance, so health signals never route back
 * through the (possibly failing) publisher. The guard only wraps publication, so
 * the bootstrap/wiring path stays uninstrumented.
 *
 * @api
 */
final class GuardedExecutionReporter implements ExecutionReporterInterface
{
    /**
     * Health-signal occurrence types emitted to the separate {@see $health} reporter.
     */
    public const SIGNAL_RECURSION_GUARDED = 'publication.recursion-guarded';
    public const SIGNAL_PUBLICATION_FAILED = 'publication.failed';

    /**
     * Internal labels describing which reporter method was being guarded.
     */
    private const KIND_OBSERVATION = 'observation';
    private const KIND_OCCURRENCE = 'occurrence';
    private const KIND_MEASUREMENT = 'measurement';
    private const KIND_ACTIVITY_START = 'activity_start';
    private const KIND_ACTIVITY_END = 'activity_end';

    /**
     * Per-Fiber recursion depth. Keyed by the executing {@see Fiber} so concurrent
     * Fibers never share a counter; the WeakMap releases entries once a Fiber is
     * detached and no longer referenced.
     *
     * @var WeakMap<object, int>
     */
    private WeakMap $fiberDepth;

    /**
     * Recursion depth for synchronous (main-scope, non-Fiber) publication.
     */
    private int $mainDepth = 0;

    public function __construct(
        private ExecutionReporterInterface $delegate,
        private ExecutionReporterInterface $health,
    ) {
        $this->fiberDepth = new WeakMap();
    }

    public function reportObservation(string $message, array $context = []): void
    {
        $this->guard(
            fn () => $this->delegate->reportObservation($message, $context),
            self::KIND_OBSERVATION,
            $message,
        );
    }

    public function reportOccurrence(string $type, array $context = []): void
    {
        $this->guard(
            fn () => $this->delegate->reportOccurrence($type, $context),
            self::KIND_OCCURRENCE,
            $type,
        );
    }

    public function reportMeasurement(string $name, int|float $value, string $unit): void
    {
        $this->guard(
            fn () => $this->delegate->reportMeasurement($name, $value, $unit),
            self::KIND_MEASUREMENT,
            $name,
        );
    }

    public function beginActivity(string $name): string
    {
        $id = $this->guard(
            fn () => $this->delegate->beginActivity($name),
            self::KIND_ACTIVITY_START,
            $name,
        );

        return \is_string($id) ? $id : '';
    }

    public function endActivity(string $activityId): void
    {
        $this->guard(
            fn () => $this->delegate->endActivity($activityId),
            self::KIND_ACTIVITY_END,
            $activityId,
        );
    }

    /**
     * Runs the delegate under the recursion guard. Nested calls (depth > 1) and
     * delegate failures are sent to the health reporter; nothing is rethrown.
     *
     * @return mixed
     */
    private function guard(\Closure $publish, string $kind, string $label): mixed
    {
        $depth = $this->enterGuard();

        try {
            if ($depth > 1) {
                $this->health->reportObservation(self::SIGNAL_RECURSION_GUARDED, [
                    'kind' => $kind,
                    'label' => $label,
                ]);

                return null;
            }

            return $publish();
        } catch (\Throwable $error) {
            $this->health->reportObservation(self::SIGNAL_PUBLICATION_FAILED, [
                'kind' => $kind,
                'label' => $label,
                'error' => $error->getMessage(),
            ]);

            return null;
        } finally {
            $this->leaveGuard();
        }
    }

    /**
     * Increments and returns the recursion depth for the calling Fiber (or the
     * main scope), starting a fresh counter for a Fiber not seen before.
     */
    private function enterGuard(): int
    {
        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return ++$this->mainDepth;
        }

        $depth = ($this->fiberDepth[$fiber] ?? 0) + 1;
        $this->fiberDepth[$fiber] = $depth;

        return $depth;
    }

    /**
     * Decrements the recursion depth for the calling Fiber (or the main scope).
     */
    private function leaveGuard(): void
    {
        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            if ($this->mainDepth > 0) {
                --$this->mainDepth;
            }

            return;
        }

        $depth = $this->fiberDepth[$fiber] ?? 0;

        if ($depth > 0) {
            $this->fiberDepth[$fiber] = $depth - 1;
        }
    }
}
