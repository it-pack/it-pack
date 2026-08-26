<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Decryption;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Decryption\CommandDecryption;
use ItPack\ConfigureIt\Contract\CommandRunnerInterface;
use ItPack\ConfigureIt\Exception\DecryptionException;
use RuntimeException;

class CommandDecryptionTest extends Unit
{
    public function testFeedsCiphertextToCommandOnStdinAndTrimsTrailingNewlines(): void
    {
        $recorder = new class () implements CommandRunnerInterface {
            /** @var list<array{list<string>, string}> */
            public array $calls = [];

            public function run(array $command, string $input = ''): string
            {
                $this->calls[] = [$command, $input];

                return "line1\nline2\r\n";
            }
        };

        $decryption = new CommandDecryption($recorder, ['age', '--decrypt', '-i', '/keys/id', '-']);

        verify($decryption->decrypt('CIPHERTEXT'))->equals("line1\nline2");
        verify(count($recorder->calls))->equals(1);
        verify($recorder->calls[0][0])->equals(['age', '--decrypt', '-i', '/keys/id', '-']);
        verify($recorder->calls[0][1])->equals('CIPHERTEXT');
    }

    public function testRunnerFailureBecomesDecryptionException(): void
    {
        $runner = new class () implements CommandRunnerInterface {
            public function run(array $command, string $input = ''): string
            {
                throw new RuntimeException('boom');
            }
        };

        $decryption = new CommandDecryption($runner, ['sops', '--decrypt', '-']);

        $this->expectException(DecryptionException::class);
        $this->expectExceptionMessage('Failed to decrypt secret: boom');

        $decryption->decrypt('x');
    }
}
