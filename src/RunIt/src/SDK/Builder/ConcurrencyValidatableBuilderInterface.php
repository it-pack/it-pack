<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Builder;

use Closure;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;

/**
 * Optional opt-in seam for assembly-boundary concurrency validation.
 *
 * Implementations may register a validator that receives the engine capability
 * and the resolved context storage during {@see \ItPack\RunIt\SDK\Builder\RunItBuilder::build()}.
 * The validator may throw (for example
 * {@see \ItPack\RunIt\Domain\Exception\IncompatibleContextStorageException}) to reject
 * the pairing, or return normally to accept it. The built-in check that rejects a
 * shared `StackContextStorage` paired with a concurrent engine runs first and cannot
 * be overridden by this hook.
 *
 * This interface is intentionally separate from {@see RunItBuilderInterface} so that
 * custom builder implementations are not required to add a method. Only builders that
 * opt in to custom concurrency validation need to implement it.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ConcurrencyValidatableBuilderInterface
{
    /**
     * Registers an optional assembly-boundary concurrency validator.
     *
     * The validator receives the engine capability and the resolved context storage
     * during {@see self::build()}. It may throw (for example
     * {@see \ItPack\RunIt\Domain\Exception\IncompatibleContextStorageException}) to
     * reject the pairing, or return normally to accept it.
     *
     * This is opt-in: pass `null` (the default) to skip custom validation.
     *
     * @param Closure(EngineCapabilityInterface, ContextStorageInterface): void|null $validator
     */
    public function withConcurrencyValidator(?Closure $validator): ConcurrencyValidatableBuilderInterface;
}
