<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Lazy;

use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;

/**
 * Lazy proxy that defers construction of an expensive handler.
 *
 * The real handler is produced by the supplied factory and instantiated on the
 * first call to {@see self::handles()} or {@see self::handle()}, and exactly
 * once. This is the lightweight equivalent of `GhostObject::newLazyGhost`
 * (intended by the plan) without adding a runtime dependency; it can be swapped
 * for the ghost-object form when `symfony/lazy-object` is adopted.
 *
 * @api
 */
final class LazyHandlerProxy implements CommandHandlerInterface
{
    private ?CommandHandlerInterface $resolved = null;

    /**
     * @param callable(): CommandHandlerInterface $factory
     */
    public function __construct(
        private $factory,
    ) {
    }

    private function resolve(): CommandHandlerInterface
    {
        if ($this->resolved === null) {
            $this->resolved = ($this->factory)();
        }

        return $this->resolved;
    }

    public function handles(): string
    {
        return $this->resolve()->handles();
    }

    public function handle(CommandInterface $command): CommandResultInterface
    {
        return $this->resolve()->handle($command);
    }
}
