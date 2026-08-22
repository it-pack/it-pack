<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Bootstrap;

use ItPack\CommandIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\CommandIt\Adapter\Common\SystemClock;
use ItPack\CommandIt\Adapter\Context\AmbientContextStorage;
use ItPack\CommandIt\Adapter\Context\FiberBoundContextStorage;
use ItPack\CommandIt\Adapter\Fiber\FiberRuntimeDetector;
use ItPack\CommandIt\Adapter\Null\NullCommandExecutionStorage;
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Adapter\Reporting\SafeReporterFactory;
use ItPack\CommandIt\Contract\Command\CommandMiddlewareInterface;
use ItPack\CommandIt\Contract\Port\ClockInterface;
use ItPack\CommandIt\Contract\Port\CommandExecutionStorageInterface;
use ItPack\CommandIt\Contract\Port\ExecutionContextProviderInterface;
use ItPack\CommandIt\Contract\Port\ExecutionReporterInterface;
use ItPack\CommandIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\CommandIt\Contract\Port\SerializerInterface;
use ItPack\CommandIt\SDK\CommandIt;

/**
 * Composition root: assembles a fully-wired {@see CommandIt} from in-box defaults.
 *
 * Every default lives in the `Adapter` layer (clocks, identifiers, reporters,
 * context storage, in-memory storage), so this factory deliberately sits in a
 * `Bootstrap` layer that is allowed to depend on both `SDK` and `Adapter` — the
 * SDK facade itself must stay adapter-free per the architecture. Pass any port to
 * override the corresponding default; unmapped ports fall back to the safe-by-default
 * wiring (including the guarded + redacting reporter).
 *
 * @api
 */
final class CommandItFactory
{
    /**
     * @param list<CommandMiddlewareInterface> $middlewares
     */
    public static function create(
        ?ClockInterface $clock = null,
        ?IdentifierGeneratorInterface $ids = null,
        ?ExecutionReporterInterface $reporter = null,
        ?ExecutionContextProviderInterface $context = null,
        ?CommandExecutionStorageInterface $storage = null,
        ?SerializerInterface $serializer = null,
        array $middlewares = [],
    ): CommandIt {
        $runtime = new FiberRuntimeDetector();

        return new CommandIt(
            $clock ?? new SystemClock(),
            $ids ?? new RandomIdentifierGenerator(),
            $reporter ?? new SafeReporterFactory()->wrap(new CollectingExecutionReporter()),
            $context ?? new FiberBoundContextStorage($runtime, new AmbientContextStorage()),
            $storage ?? new NullCommandExecutionStorage(),
            $serializer,
            middlewares: $middlewares,
        );
    }
}
