<?php

declare(strict_types=1);

namespace Tests\Unit\CLI;

use Codeception\Test\Unit;

use function file_put_contents;
use function fopen;

use ItPack\ConfigureIt\CLI\DiffCommand;
use ItPack\ConfigureIt\CLI\Output;

use function json_encode;
use function rewind;
use function str_contains;
use function stream_get_contents;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * @covers \ItPack\ConfigureIt\CLI\DiffCommand
 */
final class DiffCommandTest extends Unit
{
    public function testUsageWhenTooFewArguments(): void
    {
        $output = new Output(fopen('php://memory', 'w'));
        $code = new DiffCommand()->run([], $output);

        verify($code)->equals(1);
        verify(str_contains($this->capture($output), 'Usage: diff'))->true();
    }

    public function testMissingFileReturnsNonZero(): void
    {
        $output = new Output(fopen('php://memory', 'w'));
        $code = new DiffCommand()->run(['/no/such/a.json', '/no/such/b.json'], $output);

        verify($code)->equals(1);
        verify(str_contains($this->capture($output), 'does not exist'))->true();
    }

    public function testSummarizesDifference(): void
    {
        $a = $this->file(['feature' => ['enabled' => true], 'name' => 'a']);
        $b = $this->file(['feature' => ['enabled' => false], 'name' => 'b']);

        $output = new Output(fopen('php://memory', 'w'));
        $code = new DiffCommand()->run([$a, $b], $output);

        verify($code)->equals(0);
        verify('' !== $this->capture($output))->true();

        @unlink($a);
        @unlink($b);
    }
    private function capture(Output $output): string
    {
        /** @var resource $stream */
        $stream = $output->stream();
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function file(array $data): string
    {
        $path = sys_get_temp_dir() . '/cfg-diff-' . uniqid() . '.json';
        file_put_contents($path, (string) json_encode($data));

        return $path;
    }
}
