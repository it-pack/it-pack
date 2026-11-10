<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Builder;

use Closure;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;

/**
 * Optional opt-in seam extending the builder with assembly-boundary concurrency validation.
 *
 * Implementations register a validator that receives the engine capability and the
 * resolved context storage during {@see \ItPack\RunIt\SDK\Builder\RunItBuilderInterface::build()}.
 * The validator may throw (for example
 * {@see \ItPack\RunIt\Domain\Exception\IncompatibleContextStorageException}) to reject
 * the pairing, or return normally to accept it. The built-in check that rejects a shared
 * `StackContextStorage` paired with a concurrent engine runs first and cannot be overridden
 * by this hook.
 *
 * Custom builder implementations that do not need concurrency validation keep implementing
 * {@see RunItBuilderInterface} alone; this extended interface is purely opt-in.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExtendedRunItBuilderInterface extends RunItBuilderInterface
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
    public function withConcurrencyValidator(?Closure $validator): ExtendedRunItBuilderInterface;
}
