<?php

declare(strict_types=1);

namespace Tests\Unit\CLI;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\CLI\ConfigurationCli;
use ItPack\ConfigureIt\CLI\Output;

class ConfigurationCliTest extends Unit
{
    private function memoryOutput(): Output
    {
        return new Output(\fopen('php://memory', 'r+'));
    }

    private function read(Output $output): string
    {
        /** @var resource $stream */
        $stream = $output->stream();

        \rewind($stream);

        return (string) \stream_get_contents($stream);
    }

    private function support(string $file): string
    {
        return __DIR__ . '/../../../examples/Support/Provenance/' . $file;
    }

    public function testExplainTableReturnsSuccess(): void
    {
        $output = $this->memoryOutput();

        $code = new ConfigurationCli()->handle(
            ['configure-it', 'explain', $this->support('local.json')],
            $output,
        );

        verify($code)->equals(0);
        verify(\str_contains($this->read($output), 'db.host'))->true();
        verify(\str_contains($this->read($output), 'ADAPTER'))->true();
    }

    public function testExplainKeyShowsAdapter(): void
    {
        $output = $this->memoryOutput();

        $code = new ConfigurationCli()->handle(
            ['configure-it', 'explain', $this->support('local.json'), 'db.host'],
            $output,
        );

        verify($code)->equals(0);
        verify(\str_contains($this->read($output), 'JsonLoader'))->true();
    }

    public function testDiffShowsChangedKeys(): void
    {
        $output = $this->memoryOutput();

        $code = new ConfigurationCli()->handle(
            ['configure-it', 'diff', $this->support('base.json'), $this->support('local.json')],
            $output,
        );

        verify($code)->equals(0);
        verify(\str_contains($this->read($output), 'changed'))->true();
        verify(\str_contains($this->read($output), 'local.json overrode base.json'))->true();
    }

    public function testGraphAsciiRendersTree(): void
    {
        $output = $this->memoryOutput();

        $code = new ConfigurationCli()->handle(
            ['configure-it', 'graph', $this->support('local.json'), 'ascii'],
            $output,
        );

        verify($code)->equals(0);
        verify(\str_contains($this->read($output), 'db:'))->true();
    }

    public function testGraphDotRendersDigraph(): void
    {
        $output = $this->memoryOutput();

        $code = new ConfigurationCli()->handle(
            ['configure-it', 'graph', $this->support('local.json')],
            $output,
        );

        verify($code)->equals(0);
        verify(\str_contains($this->read($output), 'digraph configuration'))->true();
    }

    public function testHelpReturnsSuccess(): void
    {
        $output = $this->memoryOutput();

        $code = new ConfigurationCli()->handle(['configure-it', 'help'], $output);

        verify($code)->equals(0);
        verify(\str_contains($this->read($output), 'explain'))->true();
    }

    public function testUnknownCommandReturnsError(): void
    {
        $output = $this->memoryOutput();

        $code = new ConfigurationCli()->handle(['configure-it', 'frobnicate'], $output);

        verify($code)->equals(2);
        verify(\str_contains($this->read($output), 'Unknown command'))->true();
    }

    public function testMissingArgumentsReturnsError(): void
    {
        $output = $this->memoryOutput();

        $code = new ConfigurationCli()->handle(['configure-it', 'explain'], $output);

        verify($code)->equals(1);
    }

    public function testDiffWithoutTwoArgumentsReturnsUsageError(): void
    {
        $output = $this->memoryOutput();

        $code = new ConfigurationCli()->handle(['configure-it', 'diff', 'only-one'], $output);

        verify($code)->equals(1);
        verify(\str_contains($this->read($output), 'Usage: diff'))->true();
    }

    public function testGraphWithoutArgumentsReturnsUsageError(): void
    {
        $output = $this->memoryOutput();

        $code = new ConfigurationCli()->handle(['configure-it', 'graph'], $output);

        verify($code)->equals(1);
        verify(\str_contains($this->read($output), 'Usage: graph'))->true();
    }

    public function testExplainWithoutArgumentsReturnsUsageError(): void
    {
        $output = $this->memoryOutput();

        $code = new ConfigurationCli()->handle(['configure-it', 'explain'], $output);

        verify($code)->equals(1);
        verify(\str_contains($this->read($output), 'Usage: explain'))->true();
    }

    public function testNoSubcommandRendersHelpAndReturnsError(): void
    {
        $output = $this->memoryOutput();

        $code = new ConfigurationCli()->handle(['configure-it'], $output);

        verify($code)->equals(2);
        verify(\str_contains($this->read($output), 'explain'))->true();
    }
}
