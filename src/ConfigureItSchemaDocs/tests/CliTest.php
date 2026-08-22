<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSchemaDocs\Tests;

use PHPUnit\Framework\TestCase;

final class CliTest extends TestCase
{
    private string $bin;

    private string $fixture;

    protected function setUp(): void
    {
        $this->bin = __DIR__.'/../bin/configure-it-schema-docs';
        $this->fixture = __DIR__.'/Fixtures/sample-schema.php';
    }

    public function testMarkdownViaCli(): void
    {
        $result = $this->runBin('--format=markdown');

        self::assertStringContainsString('# Sample', $result['output']);
        self::assertStringContainsString('**app**', $result['output']);
    }

    public function testJsonSchemaViaCli(): void
    {
        $result = $this->runBin('--format=json-schema');

        self::assertStringContainsString('"$schema"', $result['output']);
        self::assertStringContainsString('"object"', $result['output']);
    }

    public function testMissingFileExitsWithError(): void
    {
        $result = $this->exec('--file=/does/not/exist.php');

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('File not found', $result['output']);
    }

    /**
     * @return array{output: string, code: int}
     */
    private function runBin(string $format): array
    {
        return $this->exec('--file='.escapeshellarg($this->fixture).' --title=Sample '.$format);
    }

    /**
     * @return array{output: string, code: int}
     */
    private function exec(string $args): array
    {
        $command = escapeshellarg(PHP_BINARY)
            .' '.escapeshellarg($this->bin)
            .' '.$args
            .' 2>&1';

        $output = null;
        $code = null;

        exec($command, $output, $code);

        return [
            'output' => implode("\n", (array) $output),
            'code' => (int) $code,
        ];
    }
}
