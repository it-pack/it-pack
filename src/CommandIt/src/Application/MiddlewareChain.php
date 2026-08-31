<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Application;

use ItPack\CommandIt\Contract\Application\MiddlewareChainInterface;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandMiddlewareInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\ContextEnricherInterface;

/**
 * Composes zero or more middlewares around the terminal handler in explicit order.
 *
 * The first registered middleware is the outermost; the last is closest to the
 * handler. Each middleware receives the command and a `next` callable that runs
 * the rest of the chain, ending in the handler.
 *
 * @api
 */
final readonly class MiddlewareChain implements MiddlewareChainInterface
{
    /**
     * @param list<CommandMiddlewareInterface> $middlewares
     */
    public function __construct(
        /** @var list<CommandMiddlewareInterface> */
        private array $middlewares,
    ) {
    }

    public function process(
        CommandInterface $command,
        CommandHandlerInterface $handler,
        ContextEnricherInterface $context,
    ): CommandResultInterface {
        $next = static function (CommandInterface $command) use ($handler): CommandResultInterface {
            return $handler->handle($command);
        };

        foreach (\array_reverse($this->middlewares) as $middleware) {
            $next = $this->wrap($middleware, $next, $context);
        }

        return $next($command);
    }

    /**
     * @param callable(CommandInterface): CommandResultInterface $next
     * @return callable(CommandInterface): CommandResultInterface
     */
    private function wrap(
        CommandMiddlewareInterface $middleware,
        callable $next,
        ContextEnricherInterface $context,
    ): callable {
        return static function (CommandInterface $command) use ($middleware, $next, $context): CommandResultInterface {
            return $middleware->process($command, $next, $context);
        };
    }
}
