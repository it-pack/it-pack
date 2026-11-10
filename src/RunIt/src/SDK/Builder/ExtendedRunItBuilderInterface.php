<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Builder;

use Closure;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;

interface ExtendedRunItBuilderInterface extends RunItBuilderInterface, ConcurrencyValidatableBuilderInterface
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
    public function withConcurrencyValidator(?Closure $validator): self;
}
