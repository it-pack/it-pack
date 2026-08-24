<?php

declare(strict_types=1);

/*
 * Example 02 — The vertical lifecycle, including failure, and storage.
 *
 * Shows the full received → executing → terminal state machine and how a thrown
 * handler exception is preserved (never replaced by a reporter/storage error).
 * We also plug in an in-memory storage port and retrieve the execution record
 * afterwards, proving the id is stable across emission and persistence.
 */

require_once __DIR__ . '/_bootstrap.php';

use ItPack\CommandIt\Adapter\Memory\InMemoryCommandExecutionStorage;
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Adapter\Reporting\SafeReporterFactory;
use ItPack\CommandIt\Bootstrap\CommandItFactory;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Exception\CommandExceptionInterface;
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\CommandExecution;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\Domain\ValueObject\CommandExecutionId;
use ItPack\CommandIt\SDK\CommandBuilder;
use ItPack\CommandIt\SDK\CommandDefinitionBuilder;
use ItPack\CommandIt\Adapter\Input\ArrayInputAdapter;

final class InvalidSignupException extends \RuntimeException implements CommandExceptionInterface
{
}

$storage = new InMemoryCommandExecutionStorage();
$reporter = new CollectingExecutionReporter();

$ci = CommandItFactory::create(
    reporter: (new SafeReporterFactory())->wrap($reporter),
    storage: $storage,
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
        $email = $command->input()->argument('email');

        if (!str_contains($email, '@')) {
            throw new InvalidSignupException('Not a valid email: ' . $email);
        }

        return CommandResult::succeed(['subscriber_id' => $email]);
    }
});

$dispatch = static function (string $email) use ($ci, $definition): void {
    $input = (new ArrayInputAdapter())->parse($definition, ['email' => $email]);
    $command = (new CommandBuilder())->definition($definition)->input($input)->build();
    $ci->dispatch($command);
};

section('Success path — execution is stored and retrievable');

try {
    $dispatch('ada@example.com');
} catch (\Throwable $e) {
    println('Unexpected failure: ' . $e->getMessage());
}

$handled = findOccurrence($reporter->entries(), OCC_HANDLED);
$id = executionIdFrom($handled);
println('command.handled.v1 execution_id: ' . ($id ?? '—'));

$stored = $storage->find(CommandExecutionId::fromString($id));
assert($stored instanceof CommandExecution);
println('Stored execution status : ' . $stored->status()->label());
println('Stored result payload   :');
pp($stored->result()?->payload());

section('Failure path — the original exception is preserved');

try {
    $dispatch('not-an-email');
    println('ERROR: expected the dispatch to throw');
} catch (InvalidSignupException $e) {
    println('Caught original exception: ' . $e::class . ' — "' . $e->getMessage() . '"');
}

$failed = findOccurrence($reporter->entries(), OCC_FAILED);
$id = executionIdFrom($failed);
println('command.failed.v1 execution_id: ' . ($id ?? '—'));

$stored = $storage->find(CommandExecutionId::fromString($id));
assert($stored instanceof CommandExecution);
println('Stored execution terminal status: ' . $stored->status()->label());
println('Stored result status            : ' . ($stored->result()?->status()->label() ?? '—'));

section('Takeaway');

println('A thrown handler exception is rethrown unchanged; the coordinator still');
println('records a terminal "command.failed.v1" occurrence and the storage keeps');
println('the failed execution under the same id that the occurrence reported.');
