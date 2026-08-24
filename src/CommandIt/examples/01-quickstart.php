<?php

declare(strict_types=1);

/*
 * Example 01 — The vertical happy path.
 *
 * One command, top to bottom:
 *   define it → register it → build input → dispatch → get an immutable result.
 *
 * This is the shortest path through CommandIt. Every later example builds on it.
 */

require_once __DIR__ . '/_bootstrap.php';

use ItPack\CommandIt\Adapter\Input\ArrayInputAdapter;
use ItPack\CommandIt\Bootstrap\CommandItFactory;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\Option;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\Domain\ValueObject\OptionName;
use ItPack\CommandIt\SDK\CommandBuilder;
use ItPack\CommandIt\SDK\CommandDefinitionBuilder;

section('1 · Define the "signup" command');

$definition = (new CommandDefinitionBuilder())
    ->name('signup')
    ->description('Subscribe an email address to the newsletter')
    ->argument(new Argument(ArgumentName::fromString('email'), required: true, type: 'string'))
    ->argument(new Argument(ArgumentName::fromString('name'), required: false, type: 'string'))
    ->option(new Option(OptionName::fromString('send-welcome'), required: false, type: 'bool'))
    ->option(new Option(OptionName::fromString('source'), required: false, type: 'string', defaultValue: 'web'))
    ->build();

println('Computed CLI signature: ' . $definition->signature());

section('2 · Register it with a handler');

$ci = CommandItFactory::create();

$ci->register($definition, static fn (): CommandHandlerInterface => new class () implements CommandHandlerInterface {
    public function handles(): string
    {
        return 'signup';
    }

    public function handle(CommandInterface $command): CommandResultInterface
    {
        $email = $command->input()->argument('email');
        $name = $command->input()->hasArgument('name')
            ? $command->input()->argument('name')
            : 'friend';
        $welcomed = (bool) $command->input()->option('send-welcome');

        return CommandResult::succeed([
            'subscriber_id' => $email,
            'name' => $name,
            'welcomed' => $welcomed,
            'source' => $command->input()->option('source'),
        ]);
    }
});

section('3 · Build input and dispatch');

$input = (new ArrayInputAdapter())->parse($definition, [
    'email' => 'ada@example.com',
    'name' => 'Ada',
    'send-welcome' => true,
]);

$command = (new CommandBuilder())
    ->definition($definition)
    ->input($input)
    ->build();

$result = $ci->dispatch($command);

println('Result status : ' . $result->status()->label());
println('Result payload:');
pp($result->payload());

$execution = $result->execution();
assert($execution instanceof \ItPack\CommandIt\Domain\CommandExecution);
println('Execution id      : ' . $execution->id()->toString());
println('Execution status : ' . $execution->status()->label());
println('Started / ended   : ' . $execution->startedAt() . ' → ' . ($execution->endedAt() ?? '—'));
