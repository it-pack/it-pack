<?php

declare(strict_types=1);

namespace Tests\Unit\CLI;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\CLI\ExplainCommand;
use ItPack\ConfigureIt\CLI\Output;

/**
 * @covers \ItPack\ConfigureIt\CLI\ExplainCommand
 */
final class ExplainCommandTest extends Unit
{
    private function capture(Output $output): string
    {
        /** @var resource $stream */
        $stream = $output->stream();
        \rewind($stream);

        return (string) \stream_get_contents($stream);
    }

    private function file(): string
    {
        $path = \sys_get_temp_dir() . '/cfg-explain-' . \uniqid() . '.json';
        \file_put_contents($path, (string) \json_encode(['name' => 'x', 'nested' => ['y' => 1]]));

        return $path;
    }

    public function testUsageWhenNoArguments(): void
    {
        $output = new Output(\fopen('php://memory', 'w'));
        $code = new ExplainCommand()->run([], $output);

        verify($code)->equals(1);
        verify(\str_contains($this->capture($output), 'Usage: explain'))->true();
    }

    public function testMissingFileReturnsNonZero(): void
    {
        $output = new Output(\fopen('php://memory', 'w'));
        $code = new ExplainCommand()->run(['/no/such/file.json'], $output);

        verify($code)->equals(1);
        verify(\str_contains($this->capture($output), 'does not exist'))->true();
    }

    public function testExplainsWholeGraph(): void
    {
        $path = $this->file();
        $output = new Output(\fopen('php://memory', 'w'));
        $code = new ExplainCommand()->run([$path], $output);

        verify($code)->equals(0);
        verify($this->capture($output) !== '')->true();

        @\unlink($path);
    }

    public function testExplainsSingleKey(): void
    {
        $path = $this->file();
        $output = new Output(\fopen('php://memory', 'w'));
        $code = new ExplainCommand()->run([$path, 'name'], $output);

        verify($code)->equals(0);
        verify($this->capture($output) !== '')->true();

        @\unlink($path);
    }
}
