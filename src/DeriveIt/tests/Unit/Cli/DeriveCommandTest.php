<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use ItPack\DeriveIt\Cli\DeriveCommand;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fixture\CliSampleA;
use Tests\Support\Fixture\CliSampleB;
use Tests\Support\Fixture\CliSampleTarget;
use Tests\Support\Fixture\CliSampleTraitUser;

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
        self::assertStringContainsString(
            'interface CliSampleA',
            (string) file_get_contents($dir . '/CliSampleA.interface.php'),
        );
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

    public function testRootPromotesFlatModeToFamily(): void
    {
        // `--root` forces family mode even when another mode is requested, so this
        // derives the family (not an error and not a flat derivation).
        $result = $this->runCommand(['--mode=flat', '--root=' . CliSampleA::class]);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('interface CliSampleAInterface', $result['stdout']);
    }

    public function testFamilyAdapterMode(): void
    {
        $result = $this->runCommand(['--mode=family-adapter', CliSampleA::class, CliSampleB::class]);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('interface CliSampleA', $result['stdout']);
        self::assertStringContainsString('CliSampleAAdapter', $result['stdout']);
    }

    public function testVerboseStreamsDiagnosticsToStderrWithoutTouchingStdout(): void
    {
        $plain = $this->runCommand([CliSampleTarget::class]);
        $verbose = $this->runCommand([
            '--verbose',
            '--re-expose=Tests\Unit\Cli\MissingFixtureInterface',
            CliSampleTarget::class,
        ]);

        // stdout contract is byte-identical with and without --verbose.
        self::assertSame($plain['stdout'], $verbose['stdout']);

        // stderr carries the pipeline facts: lifecycle, stages, and the warning
        // for the configured-but-unimplemented re-exposed interface.
        self::assertSame(0, $verbose['code']);
        self::assertStringContainsString('[event] derivation.completed', $verbose['stderr']);
        self::assertStringContainsString('[activity] surface.extract started', $verbose['stderr']);
        self::assertStringContainsString('[measure] methods.derived', $verbose['stderr']);
        self::assertStringContainsString(
            '[!] reExposeOwnedInterfaces entry "Tests\Unit\Cli\MissingFixtureInterface"',
            $verbose['stderr'],
        );
        self::assertStringNotContainsString('interface CliSampleTargetInterface', $verbose['stderr']);
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

    public function testBaseDirAllowsContainedOutput(): void
    {
        $base = sys_get_temp_dir() . '/derive_base_' . uniqid();
        mkdir($base);
        $file = $base . '/out.php';
        $result = $this->runCommand(['--base-dir=' . $base, '--out=' . $file, CliSampleTarget::class]);

        self::assertSame(0, $result['code']);
        self::assertFileExists($file);
        unlink($file);
        rmdir($base);
    }

    public function testBaseDirRejectsEscape(): void
    {
        $base = sys_get_temp_dir() . '/derive_base_' . uniqid();
        mkdir($base);
        $escape = $base . '/../escape.php';
        $result = $this->runCommand(['--base-dir=' . $base, '--out=' . $escape, CliSampleTarget::class]);

        self::assertSame(3, $result['code']);
        self::assertStringContainsString('escapes --base-dir', $result['stderr']);
        rmdir($base);
    }

    public function testUnresolvableBaseDirRejected(): void
    {
        $result = $this->runCommand(
            ['--base-dir=/no/such/dir/xyz', '--out=/no/such/dir/xyz/out.php', CliSampleTarget::class],
        );

        self::assertSame(3, $result['code']);
        self::assertStringContainsString('cannot resolve --base-dir', $result['stderr']);
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

    public function testDerivesFromFileByPath(): void
    {
        $file = __DIR__ . '/Fixtures/CliSampleFile.php';
        $result = $this->runCommand([$file]);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('interface CliSampleFile', $result['stdout']);
    }

    public function testFileWithoutDeclarationIsRejected(): void
    {
        $file = __DIR__ . '/Fixtures/CliSampleNoClass.php';
        $result = $this->runCommand([$file]);

        self::assertSame(2, $result['code']);
        self::assertStringContainsString('no class/interface/trait/enum', $result['stderr']);
    }

    public function testRootWithFamilyAdapterRejected(): void
    {
        $result = $this->runCommand(['--root=' . CliSampleA::class, '--mode=family-adapter', CliSampleA::class]);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('not supported with --mode=family-adapter', $result['stderr']);
    }

    public function testFlatWithMultipleTargetsRejected(): void
    {
        $result = $this->runCommand(['--mode=flat', CliSampleA::class, CliSampleB::class]);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('expects exactly one target', $result['stderr']);
    }

    public function testFamilyWithNoTargetsRejected(): void
    {
        $result = $this->runCommand(['--mode=family']);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('needs --root', $result['stderr']);
    }

    public function testFamilyAdapterWithNoTargetsRejected(): void
    {
        $result = $this->runCommand(['--mode=family-adapter']);

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('needs one or more', $result['stderr']);
    }

    public function testEmptyArgsExitsUsage(): void
    {
        $result = $this->runCommand([]);

        self::assertSame(1, $result['code']);
    }

    public function testFamilyToStdoutSeparatesMembers(): void
    {
        $result = $this->runCommand(['--mode=family', CliSampleA::class, CliSampleB::class]);

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('// --- ', $result['stdout']);
    }

    public function testFamilyToFileWithMultipleSourcesRejected(): void
    {
        $file = sys_get_temp_dir() . '/derive_it_cli_multi_' . uniqid() . '.php';
        $result = $this->runCommand(['--mode=family', '--out=' . $file, CliSampleA::class, CliSampleB::class]);

        self::assertSame(3, $result['code']);
        self::assertStringContainsString('use --out=<dir>', $result['stderr']);
        self::assertFileDoesNotExist($file);
    }

    public function testAdapterToFileNestedDirectoryIsCreated(): void
    {
        $dir = sys_get_temp_dir() . '/derive_it_cli_nested_' . uniqid();
        $file = $dir . '/nested/Adapter.php';
        $result = $this->runCommand(['--mode=adapter', '--out=' . $file, CliSampleTarget::class]);

        self::assertSame(0, $result['code']);
        self::assertFileExists($file);
        $nestedFiles = glob($dir . '/*/*.php');
        array_map('unlink', $nestedFiles === false ? [] : $nestedFiles);
        rmdir($dir . '/nested');
        rmdir($dir);
    }
}
