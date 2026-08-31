<?php

declare(strict_types=1);

// Base PHP-CS-Fixer configuration for it-pack packages.
//
// Copy this to a package's .php-cs-fixer.php (or include it and extend the
// finder/rules). The rule set below is the monorepo coding-standard baseline
// seeded from src/RunIt/.php-cs-fixer.php and src/AsyncIt/.php-cs-fixer.php.
//
// Shared across the monorepo by docs/CONSOLIDATION_PLAN.md (Phase 0).

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->notPath('_output')
    ->notPath('_generated');

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
