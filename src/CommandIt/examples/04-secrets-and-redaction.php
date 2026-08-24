<?php

declare(strict_types=1);

/*
 * Example 04 — The horizontal axis: secrets never leave the boundary.
 *
 * Middleware attaches a credential to the execution context classified as
 * Secret. CommandIt redacts any Secret-classified context entry before it is
 * published: the coordinator merges the *redacted* view into every occurrence,
 * and the SafeReporterFactory (the default) wraps your reporter in a
 * RedactingExecutionReporter too. The plaintext never reaches a sink.
 *
 * Try it: change the classification to Classification::Internal and the value
 * shows up; as Secret it is always replaced by '***'.
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
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\SDK\CommandBuilder;
use ItPack\CommandIt\SDK\CommandDefinitionBuilder;
use ItPack\CommandIt\Adapter\Input\ArrayInputAdapter;

$apiKey = 'sk_live_1234567890abcdef';

final class AuthMiddleware implements CommandMiddlewareInterface
{
    public function __construct(private string $apiKey)
    {
    }

    public function process(
        CommandInterface $command,
        callable $next,
        ContextEnricherInterface $context,
    ): CommandResultInterface {
        // The credential is attached to the execution context so any inner span
        // could consume it in-process. It is marked Secret so it is never exported.
        $apiKey = $this->apiKey;

        $context->enrich(
            static fn (CommandContextInterface $ctx): CommandContextInterface => $ctx
                ->with('api_key', $apiKey, Classification::Secret)
                ->with('tenant_id', 'acme-corp', Classification::Internal)
                ->with('trace_id', bin2hex(random_bytes(8)), Classification::Public),
        );

        return $next($command);
    }
}

$reporter = new CollectingExecutionReporter();
$ci = CommandItFactory::create(
    reporter: (new SafeReporterFactory())->wrap($reporter),
    middlewares: [new AuthMiddleware($apiKey)],
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

$input = (new ArrayInputAdapter())->parse($definition, ['email' => 'ada@example.com']);
$command = (new CommandBuilder())->definition($definition)->input($input)->build();
$ci->dispatch($command);

section('What the reporter actually received');

$handled = findOccurrence($reporter->entries(), OCC_HANDLED);
pp($handled['context'] ?? []);

section('The proof');

$redacted = ($handled['context']['api_key'] ?? null);
println('api_key in published occurrence: ' . var_export($redacted, true));
$pass = \is_string($redacted) && $redacted !== $apiKey && str_starts_with($redacted, '***');
println($pass ? 'PASS — secret redacted before publication.' : 'FAIL — secret leaked!');

section('Takeaway');

println('No reporter, adapter, or test double had to redact anything by hand.');
println('The default wiring (SafeReporterFactory) redacts Secret-classified');
println('context automatically, so a plaintext credential cannot reach a sink.');
