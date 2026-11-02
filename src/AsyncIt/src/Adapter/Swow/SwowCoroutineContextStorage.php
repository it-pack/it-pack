<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swow;

use ItPack\AsyncIt\Domain\CoroutineContextStorageTrait;
use ItPack\AsyncIt\Domain\FiberScopeSlotFactoryInterface;
use ItPack\AsyncIt\Domain\FiberScopeSlotInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use Swow\Coroutine;
use Throwable;

/**
 * Swow coroutine-aware {@see ContextStorageInterface}.
 *
 * The ambient RunIt scope is kept in a per-coroutine slot keyed by
 * {@see \Swow\Coroutine::getCid()}, so concurrent Swow coroutines never observe
 * each other's context (ADR-013). Outside a coroutine (e.g. the conformance
 * harness) it degrades to a single process-level LIFO stack, which still
 * satisfies the token protocol Assertions A requires. Attach/detach is always
 * last-in-first-out and guarded in `finally` by the scheduler.
 *
 * Shared storage logic lives in {@see \ItPack\AsyncIt\Domain\CoroutineContextStorageTrait}.
 *
 * @see https://w3id.org/itpack/runit#Execution
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swow
 * @api
 */
final class SwowCoroutineContextStorage implements ContextStorageInterface
{
    use CoroutineContextStorageTrait;

    private const int COROUTINE_UNAVAILABLE = -1;

    /**
     * @var array<int, FiberScopeSlotInterface>
     */
    private array $perCoroutine = [];

    public function __construct(private readonly FiberScopeSlotFactoryInterface $slotFactory)
    {
    }

    protected function slotFactory(): FiberScopeSlotFactoryInterface
    {
        return $this->slotFactory;
    }

    protected function slot(): FiberScopeSlotInterface
    {
        $cid = $this->coroutineId();

        if ($cid < 0) {
            return $this->fallback();
        }

        $this->perCoroutine[$cid] ??= $this->slotFactory->create();

        return $this->perCoroutine[$cid];
    }

    private function coroutineId(): int
    {
        if (!class_exists('Swow\\Coroutine')) {
            return self::COROUTINE_UNAVAILABLE;
        }

        try {
            $id = Coroutine::getCid();
        } catch (Throwable) {
            return self::COROUTINE_UNAVAILABLE;
        }

        return is_int($id) ? $id : -1;
    }
}
