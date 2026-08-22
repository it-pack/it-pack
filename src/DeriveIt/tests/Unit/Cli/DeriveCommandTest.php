<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use ItPack\DeriveIt\Cli\DeriveCommand;
use PHPUnit\Framework\TestCase;

/**
 * Sample targets live in this namespace so they are autoloadable by the CLI's
 * class_exists() resolution without touching the fixtures.
 */
class CliSampleTarget
{
    public function hello(string $name): string
    {
        return $name;
    }

    public function widget(): void
    {
    }

    public static function make(): self
    {
        return new self();
    }
}

trait CliSampleTrait
{
    public function traitDo(): void
    {
    }
}

class CliSampleTraitUser
{
    use CliSampleTrait;

    public function selfDo(): void
    {
    }
}

class CliSampleA
{
    public function a(): void
    {
    }
}

class CliSampleB
{
    public function b(): void
    {
    }
}

final class DeriveCommandTest extends TestCase
{
    private function runCommand(array $args): array
    {
        $stdout = '';
        $stderr = '';
        $captureOut = function (string $data) use (&$stdout): void {
            $stdout .= $data;
        };
        $captureErr = function (string $data) use (&$stderr): void {
            $stderr .= $data;
        };

        $code = new DeriveCommand($captureOut, $captureErr)->execute($args);

        return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    public function testFlatDerivesToStdout(): void
    {
        $result = $this->runCommand([CliSampleTarget::class]);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('interface CliSampleTarget', $result['stdout']);
        self::assertStringContainsString('hello(string $name): string', $result['stdout']);
        // Static methods are dropped by default policy.
        self::assertStringNotContainsString('make', $result['stdout']);
    }

    public function testFlatWritesToFile(): void
    {
        $file = sys_get_temp_dir() . '/derive_it_cli_' . uniqid() . '.php';
        $result = $this->runCommand(['--out=' . $file, CliSampleTarget::class]);

        self::assertSame(0, $result['code']);
        self::assertFileExists($file);
        self::assertStringContainsString('interface CliSampleTarget', (string) file_get_contents($file));
        unlink($file);
    }

    public function testPerTraitMode(): void
    {
        $result = $this->runCommand(['--mode=per-trait', CliSampleTraitUser::class]);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('interface CliSampleTraitUser', $result['stdout']);
        self::assertStringContainsString('selfDo', $result['stdout']);
        self::assertStringContainsString('traitDo', $result['stdout']);
    }

    public function testAdapterMode(): void
    {
        $result = $this->runCommand(['--mode=adapter', CliSampleTarget::class]);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('interface CliSampleTarget', $result['stdout']);
        self::assertStringContainsString('class CliSampleTargetAdapter', $result['stdout']);
        self::assertStringContainsString('implements', $result['stdout']);
    }

    public function testFamilyWritesDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/derive_it_cli_' . uniqid();
        $result = $this->runCommand(['--mode=family', '--out=' . $dir . '/', CliSampleA::class, CliSampleB::class]);

        self::assertSame(0, $result['code']);
        self::assertFileExists($dir . '/CliSampleA.interface.php');
        self::assertFileExists($dir . '/CliSampleB.interface.php');
        self::assertStringContainsString('interface CliSampleA', (string) file_get_contents($dir . '/CliSampleA.interface.php'));
        $files = glob($dir . '/*.interface.php');
        array_map('unlink', $files === false ? [] : $files);
        rmdir($dir);
    }

    public function testFamilyFromRoot(): void
    {
        $dir = sys_get_temp_dir() . '/derive_it_cli_root_' . uniqid();
        $result = $this->runCommand(['--root=' . CliSampleA::class, '--out=' . $dir . '/']);

        self::assertSame(0, $result['code']);
        self::assertFileExists($dir . '/CliSampleA.interface.php');
        $files = glob($dir . '/*.interface.php');
        array_map('unlink', $files === false ? [] : $files);
        rmdir($dir);
    }

    public function testFamilyAdapterMode(): void
    {
        $result = $this->runCommand(['--mode=family-adapter', CliSampleA::class, CliSampleB::class]);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('interface CliSampleA', $result['stdout']);
        self::assertStringContainsString('CliSampleAAdapter', $result['stdout']);
    }

    public function testUnknownTargetExitsTwo(): void
    {
        $result = $this->runCommand(['NoSuchClass_' . uniqid()]);

        self::assertSame(2, $result['code']);
        self::assertStringContainsString('derive:', $result['stderr']);
    }

    public function testUnknownOptionExitsOne(): void
    {
        $result = $this->runCommand(['--bogus=x']);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('unknown option', $result['stderr']);
    }

    public function testMissingOptionValueExitsOne(): void
    {
        $result = $this->runCommand(['--out']);

        self::assertSame(1, $result['code']);
    }

    public function testHelpExitsZeroAndPrintsUsage(): void
    {
        $result = $this->runCommand(['--help']);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('derive', $result['stdout']);
    }

    public function testConfigFlagsAreApplied(): void
    {
        // --include-static should retain the static factory on the flat surface.
        $result = $this->runCommand(['--include-static', CliSampleTarget::class]);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('make', $result['stdout']);
    }

    public function testBinEntrypointPrintsHelp(): void
    {
        if (!function_exists('proc_open') || !isset($GLOBALS['argv'])) {
            self::markTestSkipped('proc_open unavailable');
        }

        $bin = __DIR__ . '/../../../bin/derive';
        $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open([PHP_BINARY, $bin, '--help'], $spec, $pipes);
        self::assertNotFalse($proc);
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        self::assertSame(0, $code);
        self::assertStringContainsString('derive', $stdout);
    }
}
