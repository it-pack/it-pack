<?php

declare(strict_types=1);

/*
 * Example 06 — The horizontal axis: project the same command to a CLI.
 *
 * The CLI adapter is a projection, not a rewrite. The identical signup command
 * and handler from the earlier examples are registered once, then enumerated
 * as Symfony Console commands. Dispatch still runs through the same
 * CommandBus, so middleware, context, redaction, and storage all apply.
 *
 * symfony/console is a "suggest" dependency; if it is absent this example
 * explains how to install it and exits cleanly.
 */

require_once __DIR__ . '/_bootstrap.php';

use ItPack\CommandIt\Bootstrap\CommandItFactory;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Exception\CommandExceptionInterface;
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\Option;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\Domain\ValueObject\OptionName;
use ItPack\CommandIt\SDK\CommandDefinitionBuilder;
use ItPack\CommandIt\Adapter\CLI\ConsoleCommandRegistry;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

final class InvalidSignupException extends \RuntimeException implements CommandExceptionInterface
{
}

if (!class_exists(Application::class)) {
    section('symfony/console not installed');
    println('The CLI adapter needs symfony/console. Install it with:');
    println('    composer require symfony/console');
    exit(0);
}

$ci = CommandItFactory::create();

$definition = (new CommandDefinitionBuilder())
    ->name('signup')
    ->description('Subscribe an email address to the newsletter')
    ->argument(new Argument(ArgumentName::fromString('email'), required: true, type: 'string'))
    ->argument(new Argument(ArgumentName::fromString('name'), required: false, type: 'string'))
    ->option(new Option(OptionName::fromString('send-welcome'), required: false, type: 'bool'))
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

        $name = $command->input()->hasArgument('name')
            ? $command->input()->argument('name')
            : 'friend';
        $welcomed = (bool) $command->input()->option('send-welcome');

        return CommandResult::succeed([
            'subscriber_id' => $email,
            'name' => $name,
            'welcomed' => $welcomed,
        ]);
    }
});

$app = new Application('CommandIt signup demo');
$app->setAutoExit(false);
$app->addCommands((new ConsoleCommandRegistry($ci->registry(), $ci->bus()))->toConsoleCommands());

section('CLI run #1 — valid input (expect exit 0)');
$code1 = $app->run(
    new ArgvInput(['examples/06-cli-adapter.php', 'signup', 'ada@example.com', 'Ada', '--send-welcome']),
    new ConsoleOutput(),
);
println('exit code: ' . $code1);

section('CLI run #2 — invalid email, handler throws (expect exit 1)');
$code2 = $app->run(
    new ArgvInput(['examples/06-cli-adapter.php', 'signup', 'not-an-email']),
    new ConsoleOutput(),
);
println('exit code: ' . $code2);

section('Takeaway');

println('The CLI command is a thin projection: same definition, same handler,');
println('same bus. --help, argument validation, and exit codes come for free.');
