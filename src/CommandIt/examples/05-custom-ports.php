<?php

declare(strict_types=1);

/*
 * Example 05 — The horizontal axis: swap the ports, keep the model.
 *
 * CommandIt depends on ports, not concrete infrastructure. Here we replace four
 * of them for deterministic, inspectable behavior:
 *   • ClockInterface        → a frozen clock (stable timestamps, known duration)
 *   • IdentifierGenerator   → a sequential id generator (predictable lookup key)
 *   • SerializerInterface   → a JSON snapshot normalizer (objects → primitives)
 *   • StorageInterface      → in-memory storage (retrieve by the known id)
 * None of the command, handler, or middleware code changes.
 */

require_once __DIR__ . '/_bootstrap.php';

use ItPack\CommandIt\Adapter\Memory\InMemoryCommandExecutionStorage;
use ItPack\CommandIt\Bootstrap\CommandItFactory;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Exception\SerializationException;
use ItPack\CommandIt\Contract\Port\ClockInterface;
use ItPack\CommandIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\CommandIt\Contract\Port\SerializerInterface;
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\CommandExecution;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\Domain\ValueObject\CommandExecutionId;
use ItPack\CommandIt\SDK\CommandBuilder;
use ItPack\CommandIt\SDK\CommandDefinitionBuilder;
use ItPack\CommandIt\Adapter\Input\ArrayInputAdapter;

final class FrozenClock implements ClockInterface
{
    private int $mono = 1_000_000_000;

    public function __construct(private int $now = 1_700_000_000)
    {
    }

    public function nowSeconds(): int
    {
        return $this->now;
    }

    public function monotonicNanoseconds(): int
    {
        $value = $this->mono;
        $this->mono += 5_000_000; // every reading is 5 ms later

        return $value;
    }
}

final class SequentialIdGenerator implements IdentifierGeneratorInterface
{
    private int $n = 0;

    public function generate(): string
    {
        return 'exec-' . (++$this->n);
    }
}

final class JsonSnapshotSerializer implements SerializerInterface
{
    public function normalize(mixed $data): mixed
    {
        if (is_object($data)) {
            $json = json_encode($data);

            if ($json === false) {
                throw new SerializationException('Cannot encode object: ' . json_last_error_msg());
            }

            $decoded = json_decode($json, true);

            if (!is_array($decoded)) {
                throw new SerializationException('Object did not normalize to an array.');
            }

            return $decoded;
        }

        if (is_array($data)) {
            return array_map(fn (mixed $value): mixed => $this->normalize($value), $data);
        }

        return $data;
    }

    public function contentType(): string
    {
        return 'application/json';
    }
}

final class Subscriber
{
    public function __construct(
        public string $email,
        public bool $welcomed,
    ) {
    }
}

$storage = new InMemoryCommandExecutionStorage();

$ci = CommandItFactory::create(
    clock: new FrozenClock(),
    ids: new SequentialIdGenerator(),
    storage: $storage,
    serializer: new JsonSnapshotSerializer(),
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
        return CommandResult::succeed(new Subscriber($command->input()->argument('email'), false));
    }
});

$input = (new ArrayInputAdapter())->parse($definition, ['email' => 'ada@example.com']);
$command = (new CommandBuilder())->definition($definition)->input($input)->build();
$result = $ci->dispatch($command);

section('Returned result keeps the rich object');

println('payload type : ' . get_debug_type($result->payload()));
pp($result->payload());

section('Stored execution under the predictable id "exec-1"');

$stored = $storage->find(CommandExecutionId::fromString('exec-1'));
assert($stored instanceof CommandExecution);
println('id           : ' . $stored->id()->toString());
println('startedAt    : ' . $stored->startedAt());
println('endedAt      : ' . ($stored->endedAt() ?? '—'));
println('stored payload type : ' . get_debug_type($stored->result()?->payload()));
pp($stored->result()?->payload());

section('Takeaway');

println('Swapping ports changed timing, identity, storage, and serialization,');
println('but the command definition, handler, and result semantics are identical.');
