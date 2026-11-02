<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Revolt;

use Fiber;
use ItPack\AsyncIt\Domain\CoroutineContextStorageTrait;
use ItPack\AsyncIt\Domain\FiberScopeSlotFactoryInterface;
use ItPack\AsyncIt\Domain\FiberScopeSlotInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use WeakMap;

/**
 * Revolt Fiber-aware {@see ContextStorageInterface}.
 *
 * The ambient RunIt scope is kept in a per-Fiber slot (a {@see \WeakMap} keyed
 * by the active {@see \Fiber}), so concurrent Fibers never observe each other's
 * context (ADR-013). Outside a Fiber (e.g. the conformance harness) it degrades
 * to a single process-level LIFO stack, which still satisfies the token protocol
 * Assertions A requires. Attach/detach is always last-in-first-out and guarded
 * in `finally` by the scheduler.
 *
 * Shared storage logic lives in {@see \ItPack\AsyncIt\Domain\CoroutineContextStorageTrait}.
 *
 * @see https://w3id.org/itpack/runit#Execution
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-revolt
 * @api
 */
final class RevoltFiberContextStorage implements ContextStorageInterface
{
    use CoroutineContextStorageTrait;

    /**
     * @param WeakMap<Fiber<mixed, mixed, mixed, mixed>, FiberScopeSlotInterface> $perFiber
     */
    public function __construct(private WeakMap $perFiber, private readonly FiberScopeSlotFactoryInterface $slotFactory)
    {
    }

    protected function slotFactory(): FiberScopeSlotFactoryInterface
    {
        return $this->slotFactory;
    }

    protected function slot(): FiberScopeSlotInterface
    {
        /** @var Fiber<mixed, mixed, mixed, mixed>|null $fiber */
        $fiber = Fiber::getCurrent();

        if ($fiber === null) {
            return $this->fallback();
        }

        $this->perFiber[$fiber] ??= $this->slotFactory->create();

        return $this->perFiber[$fiber];
    }
}
