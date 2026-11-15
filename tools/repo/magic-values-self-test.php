<?php

declare(strict_types=1);

if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php magic-values-self-test.php AUTOLOAD\n");
    exit(2);
}
$autoload = realpath($argv[1]);
$temp = sys_get_temp_dir() . '/magic-values-' . bin2hex(random_bytes(8));
mkdir($temp, 0700);
$runner = __DIR__ . '/magic-values.php';
$invoke = static function (array $args, ?string $cwd = null) use ($runner): array {
    $process = proc_open([PHP_BINARY, $runner, ...$args], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start runner');
    }
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $out, $err];
};
$run = static fn (array $args): array => $invoke([$autoload, ...$args]);
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
try {
    mkdir("$temp/src");
    mkdir("$temp/src/vendor");
    $source = "<?php\nfunction example() { return ['sensitive-token', 0x2a, 3.5]; }\n";
    file_put_contents("$temp/src/A.php", $source);
    file_put_contents("$temp/src/vendor/bad.php", '<?php invalid syntax !');
    $policy = ['version' => 1, 'include' => ['src']];
    $save = static function (array $p) use ($temp): void {
        file_put_contents("$temp/policy.json", json_encode($p, JSON_THROW_ON_ERROR));
    };
    $save($policy);
    [$code, $help, $error] = $invoke(['--help']);
    $check($code === 0 && str_contains($help, 'Usage:') && $error === '', 'Standalone help failed');
    [$code, $out, $error] = $invoke(["$temp/missing-autoload.php", "$temp/policy.json"]);
    $check($code === 2 && $out === '' && str_starts_with($error, 'magic-values: '), 'Missing autoloader contract changed');
    file_put_contents("$temp/empty-autoload.php", '<?php');
    [$code, $out, $error] = $invoke(["$temp/empty-autoload.php", "$temp/policy.json"]);
    $check($code === 2 && $out === '' && str_starts_with($error, 'magic-values: '), 'Missing dependency contract changed');
    unlink("$temp/empty-autoload.php");
    foreach ([['--root='], ['--json=1'], ['--'], ['--root', $temp], ['--root=.', '--root=.'], ['--json', '--json']] as $options) {
        [$code, $out, $error] = $run(["$temp/policy.json", ...$options]);
        $check($code === 2 && $out === '' && str_starts_with($error, 'magic-values: '), 'Invalid option contract changed');
    }
    foreach (['{"version":1,"include":{"0":"src"}}', '{"version":1,"include":["src"],"exclude":{}}', '{"version":1,"include":["src"],"mappings":{}}'] as $invalidPolicy) {
        file_put_contents("$temp/policy.json", $invalidPolicy);
        $check($run(["$temp/policy.json"])[0] === 2, 'Object accepted where policy list required');
    }
    $save($policy);
    [$code, $output, $error] = $run(["$temp/policy.json", '--json']);
    $check($code === 1 && $error === '', "Scan failed: $error");
    $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    $check(count($data['findings']) >= 3 && !str_contains($output, 'sensitive-token'), 'Missing findings or leaked literal');
    $check($run(["$temp/policy.json", '--json'])[1] === $output, 'Report is not deterministic');
    $check($run(["$temp/policy.json", "--root=$temp", '--json']) === [1, $output, ''], 'Option order changed report or exit status');
    $check($invoke([$autoload, 'policy.json', '--json', '--root=.'], $temp) === [1, $output, ''], 'Relative working-directory behavior changed');
    $check(file_get_contents("$temp/src/A.php") === $source, 'Source was modified');
    file_put_contents("$temp/src/B.php", $source);
    $policy['min_occurrences'] = 2;
    $save($policy);
    [$code, $aggregated, $error] = $run(["$temp/policy.json", '--json', "--root=$temp"]);
    $groups = json_decode($aggregated, true, 512, JSON_THROW_ON_ERROR)['groups'];
    $check($code === 1 && count($groups) >= 3, "Cross-file aggregation failed: $error");
    foreach ($groups as $group) {
        $check($group['occurrences'] === 2 && $group['files'] === ['src/A.php', 'src/B.php'], 'Incorrect group locations or count');
    }
    $policy['min_occurrences'] = 3;
    $save($policy);
    $check($run(["$temp/policy.json"])[0] === 0, 'Threshold not applied');
    $policy['min_occurrences'] = 0;
    $save($policy);
    $check($run(["$temp/policy.json"])[0] === 2, 'Invalid threshold accepted');
    unset($policy['min_occurrences']);
    unlink("$temp/src/B.php");
    file_put_contents("$temp/src/B.php", '<?php function other() { $delay = 42; $size = 42; $hex = 0x2a; $float = 42.0; }');
    $save($policy);
    [$code, $numericReport] = $run(["$temp/policy.json", '--json']);
    $numericGroups = json_decode($numericReport, true, 512, JSON_THROW_ON_ERROR)['groups'];
    $numericGroups = array_values(array_filter($numericGroups, static fn ($group) => in_array($group['kind'], ['int', 'float'], true)));
    $decimalGroups = array_values(array_filter($numericGroups, static fn ($group) => $group['sha256'] === hash('sha256', '42')));
    $check($code === 1 && count($decimalGroups) === 2 && $decimalGroups[0]['hint_sha256'] !== $decimalGroups[1]['hint_sha256'], 'Distinct numeric hints conflated');
    $check(count(array_filter($numericGroups, static fn ($group) => $group['sha256'] === hash('sha256', '0x2a') && $group['kind'] === 'int')) >= 1, 'Hexadecimal token lost');
    $check(count(array_filter($numericGroups, static fn ($group) => $group['sha256'] === hash('sha256', '42.0') && $group['kind'] === 'float')) === 1, 'Float token or kind lost');
    unlink("$temp/src/B.php");
    $first = $data['findings'][0];
    $policy['mappings'] = [['path' => $first['path'], 'line' => $first['line'], 'kind' => $first['kind'], 'sha256' => $first['sha256'], 'symbol' => '\\Example\\Names::VALUE']];
    $save($policy);
    [$code, $output, $error] = $run(["$temp/policy.json", '--json']);
    $check($code === 1 && str_contains($output, 'Example'), "Valid mapping rejected: $error");
    foreach (['\\Example\\\\Names::VALUE', '\\Example\\::VALUE'] as $invalidSymbol) {
        $invalidMapping = $policy;
        $invalidMapping['mappings'][0]['symbol'] = $invalidSymbol;
        $save($invalidMapping);
        $check($run(["$temp/policy.json"])[0] === 2, 'Malformed mapping symbol accepted');
    }
    $save($policy);
    [$code, $text] = $run(["$temp/policy.json"]);
    $check($code === 1 && str_contains($text, ' suggested \\Example\\Names::VALUE') && !str_contains($text, 'sensitive-token'), 'Text report changed or leaked literal');
    $policy['min_occurrences'] = 2;
    $save($policy);
    $check($run(["$temp/policy.json"])[0] === 0, 'Valid filtered mapping rejected');
    $policy['mappings'][0]['line'] = 123;
    $save($policy);
    $check($run(["$temp/policy.json"])[0] === 2, 'Stale mapping accepted before filtering');
    unset($policy['min_occurrences']);
    $policy['mappings'][0]['line'] = 2;
    file_put_contents("$temp/src/A.php", "<?php\nfunction example() { return ['sensitive-token', 'sensitive-token']; }\n");
    $policy['mappings'][0]['kind'] = 'string';
    $policy['mappings'][0]['sha256'] = hash('sha256', "'sensitive-token'");
    $save($policy);
    $check($run(["$temp/policy.json"])[0] === 2, 'Ambiguous mapping accepted');
    unset($policy['mappings']);
    $save($policy);
    file_put_contents("$temp/src/A.php", '<?php ' . str_repeat(' ', 1048576));
    $check($run(["$temp/policy.json"])[0] === 2, 'Oversized source accepted');
    file_put_contents("$temp/src/A.php", '<?php function example() {}');
    $check($run(["$temp/policy.json"]) === [0, '', ''], 'Empty scan process contract changed');
    file_put_contents("$temp/src/A.php", '<?php function {');
    $check($run(["$temp/policy.json"])[0] === 2, 'Parse error accepted');
    file_put_contents("$temp/src/A.php", '<?php return 1;');
    symlink("$temp/src/A.php", "$temp/src/link.php");
    $check($run(["$temp/policy.json"])[0] === 2, 'Symlink accepted');
    unlink("$temp/src/link.php");
    file_put_contents("$temp/src/bad\nname.php", '<?php return 1;');
    $check($run(["$temp/policy.json"])[0] === 2, 'Unsafe filename accepted');
    unlink("$temp/src/bad\nname.php");
    $policy['include'] = ['../outside'];
    $save($policy);
    $check($run(["$temp/policy.json"])[0] === 2, 'Path traversal accepted');
    $check($run(["$temp/policy.json", '--apply'])[0] === 2, 'Apply accepted');
    [$code, , $error] = $run(["$temp/policy.json", '--json', '--json']);
    $check($code === 2 && str_contains($error, 'Invalid or duplicate option'), 'Duplicate option accepted');
    file_put_contents("$temp/policy.json", str_repeat(' ', 1048577));
    $check($run(["$temp/policy.json"])[0] === 2, 'Oversized policy accepted');
    fwrite(STDOUT, "Magic-value integration self-tests passed.\n");
} finally {
    if (is_file("$temp/empty-autoload.php")) {
        unlink("$temp/empty-autoload.php");
    }
    if (is_file("$temp/src/B.php")) {
        unlink("$temp/src/B.php");
    }
    unlink("$temp/src/vendor/bad.php");
    rmdir("$temp/src/vendor");
    unlink("$temp/src/A.php");
    rmdir("$temp/src");
    unlink("$temp/policy.json");
    rmdir($temp);
}
