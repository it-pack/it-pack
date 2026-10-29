<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->notPath('_output')
    ->notPath('_generated');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'ordered_imports' => true,
    ])
    ->setFinder($finder);
