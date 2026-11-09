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
    fwrite(STDOUT, "Downstream verification self-tests passed.\n");
} finally {
    removeTree($temp);
}
