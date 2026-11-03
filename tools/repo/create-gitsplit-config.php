<?php

declare(strict_types=1);



// file_put_contents($base.'/gitsplit-template.json', json_encode($config, JSON_PRETTY_PRINT));


function camelToUnderscore($string, $us = "-") {
    return strtolower(preg_replace('/(?<!^)[A-Z]+|(?<!^|\d)[\d]+/', $us.'$0', $string));
}

function repoFromComposer(string $composerFile): string
{
    $composer = json_decode(file_get_contents($composerFile), true);

    return explode('/', $composer['name'])[1];
}


$base = __DIR__;
$root = __DIR__.'/../..';
$src = $root.'/src';
$gitsplitFile = $root.'/.gitsplit.yml ';
$gitsplitTempFile = $base.'/.gitsplit.yml ';

$org = 'it-pack';
$orgUrl = 'https://${GH_TOKEN}@github.com/it-pack/';

$template = json_decode(file_get_contents($base.'/gitsplit-template.json'), true);

$splits = [];

$iterator = new DirectoryIterator($src);

foreach ($iterator as $file) {
    if ($file->isDot() || !$file->isDir()) {
        continue;
    }

    $packageName = $file->getFilename();

    $composerFile = $file->getPathname().'/composer.json';

    $repoName = file_exists($composerFile)
        ? repoFromComposer($composerFile)
        : camelToUnderscore($packageName);

    echo $repoName."\n";

    $splits[] = [
        'prefix' => 'src/'.$packageName,
        'target' => $orgUrl.'/'.$repoName,
    ];

}

$template['splits'] = $splits;

yaml_emit_file($gitsplitTempFile, $template);

echo file_get_contents($gitsplitTempFile);

copy($gitsplitTempFile, $gitsplitFile);