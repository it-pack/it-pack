<?php

declare(strict_types=1);

namespace Tests\Unit\CLI;

use Codeception\Test\Unit;

use function fopen;

use InvalidArgumentException;

use function is_resource;

use ItPack\ConfigureIt\CLI\Output;

use function rewind;
use function stream_get_contents;

/**
 * @covers \ItPack\ConfigureIt\CLI\Output
 */
final class OutputTest extends Unit
{
    public function testWriteAndWritelnUseStream(): void
    {
        $output = new Output(fopen('php://memory', 'r+'));
        $output->write('hello');
        $output->writeln('world');

        /** @var resource $stream */
        $stream = $output->stream();
        rewind($stream);
        $content = (string) stream_get_contents($stream);

        verify($content)->equals('hello' . 'world' . PHP_EOL);
    }

    public function testWritelnDefaultsToEmptyLine(): void
    {
        $output = new Output(fopen('php://memory', 'r+'));
        $output->writeln();

        /** @var resource $stream */
        $stream = $output->stream();
        rewind($stream);

        verify((string) stream_get_contents($stream))->equals(PHP_EOL);
    }

    public function testStdoutReturnsWritableStream(): void
    {
        $output = Output::stdout();

        self::assertInstanceOf(Output::class, $output);
        verify(is_resource($output->stream()))->true();
    }

    public function testNonResourceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Output requires a stream resource.');

        new Output('not-a-resource');
    }
}
