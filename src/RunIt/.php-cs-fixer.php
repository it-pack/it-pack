<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->notPath('bootstrap.php')
    ->notPath('_generated')
    ->notPath('tests/Support/_generated')
    ->notPath('tests/_output');

$config = new PhpCsFixer\Config();

return $config
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP84Migration' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'no_leading_import_slash' => true,
        'array_syntax' => ['syntax' => 'short'],
        'single_quote' => true,
        'no_superfluous_elseif' => true,
        'no_useless_return' => true,
        'no_trailing_comma_in_singleline' => true,
        'trailing_comma_in_multiline' => true,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'],
    ])
    ->setFinder($finder);
