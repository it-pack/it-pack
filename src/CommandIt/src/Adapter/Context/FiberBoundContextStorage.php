<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Context;

use ItPack\CommandIt\Contract\Domain\CommandContextInterface;
use ItPack\CommandIt\Contract\Port\ContextTokenInterface;
use ItPack\CommandIt\Contract\Port\ExecutionContextProviderInterface;
use ItPack\CommandIt\Contract\Port\RuntimeContextDetectorInterface;
use WeakMap;

/**
 * Per-Fiber command context storage.
 *
 * Each Fiber keeps its own context stack, so a context entered in one Fiber is
 * invisible to every other Fiber and to the main stack; concurrent Fibers never
 * share context. Associations are held in a WeakMap keyed by the Fiber object,
 * so once a Fiber is detached and no longer referenced it becomes eligible for
 * collection without manual cleanup. The synchronous (main) stack is delegated
 * to the ambient {@see AmbientContextStorage} passed in.
 *
 * {@see self::reset()} clears the *calling* Fiber's stack only. The WeakMap is not
 * iterable, so a process-wide reset is impossible by design; see ADR 0008 for the
 * persistent-worker guidance (call `reset()` between requests on the main scope).
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class FiberBoundContextStorage implements ExecutionContextProviderInterface
{
    /** @var WeakMap<object, ContextStack> */
    private WeakMap $stacks;

    public function __construct(
        private RuntimeContextDetectorInterface $detector,
        private AmbientContextStorage $ambient,
    ) {
        $this->stacks = new WeakMap();
    }

    /**
     * Returns the provider that owns the current call stack: the ambient provider
     * for synchronous (main) code, or a per-Fiber {@see ContextStack} otherwise.
     */
    private function provider(): ExecutionContextProviderInterface
    {
        $fiber = $this->detector->currentFiber();

        if ($fiber === null) {
            return $this->ambient;
        }

        if (!isset($this->stacks[$fiber])) {
            $this->stacks[$fiber] = new ContextStack();
        }

        return $this->stacks[$fiber];
    }

    public function current(): ?CommandContextInterface
    {
        return $this->provider()->current();
    }

    public function enter(CommandContextInterface $context): ContextTokenInterface
    {
        return $this->provider()->enter($context);
    }

    public function leave(ContextTokenInterface $token): void
    {
        $this->provider()->leave($token);
    }

    public function enrich(\Closure $patch): void
    {
        $this->provider()->enrich($patch);
    }

    /**
     * Clears the context stack of the *calling* Fiber only.
     *
     * The main (synchronous) scope delegates to the ambient provider; a Fiber
     * scope clears only its own WeakMap entry. A process-wide reset is intentionally
     * impossible — the WeakMap is not iterable — so callers must reset each scope
     * they own (see ADR 0008). This is an operational concern, deliberately absent
     * from the {@see ExecutionContextProviderInterface} port.
     */
    public function reset(): void
    {
        $fiber = $this->detector->currentFiber();

        if ($fiber === null) {
            $this->ambient->reset();

            return;
        }

        if (isset($this->stacks[$fiber])) {
            $this->stacks[$fiber]->reset();
        }
    }
}
