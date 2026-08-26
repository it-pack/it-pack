<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->notPath('bootstrap.php')
    ->notPath('Support/_generated')
    ->notPath('_output');

$config = new PhpCsFixer\Config();

return $config
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP84Migration' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_superfluous_elseif' => true,
        'no_useless_return' => true,
        'no_trailing_comma_in_singleline' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'],
        'yoda_style' => ['equal' => true, 'identical' => true],
        'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => true],
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'constant',
                'property',
                'construct',
                'method_public_static',
                'method_public',
                'method_protected_static',
                'method_protected',
                'method_private_static',
                'method_private',
            ],
        ],
    ])
    ->setFinder($finder);
