<?php

declare(strict_types=1);

/*
 * Example 03 — The horizontal axis: middleware and context enrichment.
 *
 * Middleware wraps every command regardless of domain. Here two middlewares
 * demonstrate the two jobs they are for:
 *   • CorrelationMiddleware attaches classified context (trace_id, tenant_id)
 *     that the coordinator merges into every emitted occurrence.
 *   • TimingMiddleware measures the handler and reports an observation.
 * The context seam is copy-on-write: middleware enriches without owning the
 * stack token, so the executor still restores the previous context correctly.
 */

require_once __DIR__ . '/_bootstrap.php';

use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Adapter\Reporting\SafeReporterFactory;
use ItPack\CommandIt\Bootstrap\CommandItFactory;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandMiddlewareInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\ContextEnricherInterface;
use ItPack\CommandIt\Contract\Domain\Classification;
use ItPack\CommandIt\Contract\Domain\CommandContextInterface;
use ItPack\CommandIt\Contract\Port\ExecutionReporterInterface;
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\SDK\CommandBuilder;
use ItPack\CommandIt\SDK\CommandDefinitionBuilder;
use ItPack\CommandIt\Adapter\Input\ArrayInputAdapter;

final class CorrelationMiddleware implements CommandMiddlewareInterface
{
    public function process(
        CommandInterface $command,
        callable $next,
        ContextEnricherInterface $context,
    ): CommandResultInterface {
        $traceId = bin2hex(random_bytes(8));

        $context->enrich(
            static fn (CommandContextInterface $ctx): CommandContextInterface => $ctx
                ->with('trace_id', $traceId, Classification::Public)
                ->with('tenant_id', 'acme-corp', Classification::Internal),
        );

        return $next($command);
    }
}

final class TimingMiddleware implements CommandMiddlewareInterface
{
    public function __construct(private ExecutionReporterInterface $reporter)
    {
    }

    public function process(
        CommandInterface $command,
        callable $next,
        ContextEnricherInterface $context,
    ): CommandResultInterface {
        $start = hrtime(true);
        $result = $next($command);
        $millis = (hrtime(true) - $start) / 1_000_000;

        $this->reporter->reportObservation('handler.duration_ms', [
            'command' => $command->name()->toString(),
            'ms' => $millis,
        ]);

        return $result;
    }
}

$reporter = new CollectingExecutionReporter();
$ci = CommandItFactory::create(
    reporter: (new SafeReporterFactory())->wrap($reporter),
    middlewares: [
        new TimingMiddleware($reporter),
        new CorrelationMiddleware(),
    ],
);

$definition = (new CommandDefinitionBuilder())
    ->name('signup')
    ->description('Subscribe an email address to the newsletter')
    ->argument(new Argument(ArgumentName::fromString('email'), required: true, type: 'string'))
    ->build();

$ci->register($definition, static fn (): CommandHandlerInterface => new class () implements CommandHandlerInterface {
    public function handles(): string
    {
        return 'signup';
    }

    public function handle(CommandInterface $command): CommandResultInterface
    {
        return CommandResult::succeed(['subscriber_id' => $command->input()->argument('email')]);
    }
});

section('Registered middleware order (outer → inner): Timing, Correlation');

$input = (new ArrayInputAdapter())->parse($definition, ['email' => 'ada@example.com']);
$command = (new CommandBuilder())->definition($definition)->input($input)->build();
$ci->dispatch($command);

section('The handler.duration_ms observation (from TimingMiddleware)');

foreach ($reporter->entries() as $entry) {
    if (($entry['type'] ?? null) === 'observation') {
        pp($entry);
    }
}

section('Context attached by CorrelationMiddleware flowed into the occurrence');

$handled = findOccurrence($reporter->entries(), OCC_HANDLED);
println('command.handled.v1 context:');
pp($handled['context'] ?? []);

section('Takeaway');

println('trace_id (Public) and tenant_id (Internal) were attached by middleware');
println('and the coordinator merged them into the emitted occurrence context.');
println('Secrets attached the same way are redacted automatically — see 04.');
