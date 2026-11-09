<?php
declare(strict_types=1);

require __DIR__ . '/downstream-test.php';

$temp = sys_get_temp_dir() . '/downstream-self-test-' . bin2hex(random_bytes(8));
mkdir($temp, 0700);
try {
    $checkout = "$temp/checkout";
    $root = "$temp/consumer";
    mkdir("$checkout/src/Contract/Port", 0700, true);
    mkdir("$root/vendor/it-pack", 0700, true);
    file_put_contents("$checkout/src/Contract/Port/ContextTokenInterface.php", '<?php namespace ItPack\RunIt\Contract\Port; interface ContextTokenInterface {}');
    symlink($checkout, "$root/vendor/it-pack/run-it");
    $autoload = <<<'PHP'
<?php
namespace Composer;
class InstalledVersions {
    public static function getPrettyVersion($name) { return '1.1.2'; }
    public static function getInstallPath($name) { return __DIR__ . '/it-pack/run-it'; }
}
require __DIR__ . '/it-pack/run-it/src/Contract/Port/ContextTokenInterface.php';
PHP;
    file_put_contents("$root/vendor/autoload.php", $autoload);
    $runner = __DIR__ . '/downstream-test.php';
    $run = static fn (string $path, string $version) => command([PHP_BINARY, $runner, '--verify', $root, $path, $version], $temp);
    $success = $run($checkout, '1.1.2');
    if ($success['exit_code'] !== 0 || (json_decode($success['stdout'], true)['version'] ?? '') !== '1.1.2') {
        throw new RuntimeException('Correct checkout rejected: ' . $success['stderr']);
    }
    if ($run($checkout, '1.2.0')['exit_code'] === 0 || $run($temp, '1.1.2')['exit_code'] === 0) {
        throw new RuntimeException('Wrong version or checkout accepted');
    }
    mkdir("$temp/other/src/Contract/Port", 0700, true);
    copy("$checkout/src/Contract/Port/ContextTokenInterface.php", "$temp/other/src/Contract/Port/ContextTokenInterface.php");
    file_put_contents("$root/vendor/autoload.php", str_replace("__DIR__ . '/it-pack/run-it/src/Contract/Port/ContextTokenInterface.php'", var_export("$temp/other/src/Contract/Port/ContextTokenInterface.php", true), $autoload));
    if ($run($checkout, '1.1.2')['exit_code'] === 0) {
        throw new RuntimeException('Wrong autoloaded class accepted');
    }
    file_put_contents("$root/vendor/autoload.php", $autoload);
    unlink("$root/vendor/it-pack/run-it");
    mkdir("$root/vendor/it-pack/run-it");
    if ($run($checkout, '1.1.2')['exit_code'] === 0) {
        throw new RuntimeException('Copied package accepted in place of checkout');
    }
    $composer = "$temp/composer.php";
    $marker = "$temp/composer-ran";
    file_put_contents($composer, '<?php file_put_contents(' . var_export($marker, true) . ', "yes"); fwrite(STDERR, "bad\\xFFoutput"); exit(1);');
    $output = "$temp/results.json";
    file_put_contents($output, 'existing evidence');
    $args = [PHP_BINARY, $runner, '--package=RunItThirdPartySample', "--composer=$composer", "--output=$output"];
    $existing = command($args, $temp);
    if ($existing['exit_code'] === 0 || file_exists($marker) || file_get_contents($output) !== 'existing evidence') {
        throw new RuntimeException('Existing output was not rejected before running Composer');
    }
    $args[4] = "--output=$temp/missing/results.json";
    if (command($args, $temp)['exit_code'] === 0 || file_exists($marker)) {
        throw new RuntimeException('Invalid output path was not rejected before running Composer');
    }
    unlink($output);
    $args[4] = "--output=$output";
    $invalidUtf8 = command($args, $temp);
    $summary = json_decode(file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
    if ($invalidUtf8['exit_code'] === 0 || !str_contains($summary['results'][0]['steps']['install']['stderr'] ?? '', "bad\u{FFFD}output")) {
        throw new RuntimeException('Invalid UTF-8 output was not preserved with substitution');
    }
    $stdoutResult = command(array_slice($args, 0, 4), $temp);
    $stdoutSummary = json_decode($stdoutResult['stdout'], true, 512, JSON_THROW_ON_ERROR);
    if (($stdoutSummary['results'][0]['status'] ?? '') !== 'failed') {
        throw new RuntimeException('JSON was not written to stdout without --output');
    }
    if (is_writable('/dev/full')) {
        $stderr = tmpfile();
        $process = proc_open(array_slice($args, 0, 4), [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/full', 'w'], 2 => $stderr], $pipes, $temp);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start stdout failure control');
        }
        $exitCode = proc_close($process);
        rewind($stderr);
        if ($exitCode === 0 || !str_contains(stream_get_contents($stderr), 'Cannot write output file')) {
            throw new RuntimeException('Failed stdout write was not reported');
        }
        fclose($stderr);
    }
    $repo = dirname(__DIR__, 2);
    $repoOutput = $repo . '/.downstream-self-test-' . bin2hex(random_bytes(8)) . '.json';
    try {
        $args[4] = "--output=$repoOutput";
        command($args, $temp);
        $repoSummary = json_decode(file_get_contents($repoOutput), true, 512, JSON_THROW_ON_ERROR);
        if (str_contains($repoSummary['checkout']['status'], basename($repoOutput))) {
            throw new RuntimeException('Output file contaminated checkout status');
        }
    } finally {
        if (is_file($repoOutput)) {
            unlink($repoOutput);
        }
    }
    fwrite(STDOUT, "Downstream verification self-tests passed.\n");
} finally {
    removeTree($temp);
}
