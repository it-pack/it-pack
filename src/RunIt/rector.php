<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\AnnotationsToAttributes\Rector\Class_\CoversAnnotationWithValueToAttributeRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/examples',
    ])
    ->withSkipPath(
        __DIR__ . '/tests/Support/_generated',
    )
    ->withSkipPath(
        __DIR__ . '/tests/Support/Fixture',
    )
    ->withPhpSets(php85: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_85,
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        // NAMED_ARGS an introduce unwanted behavior, may be run on individual files if desired, but not on the package as a whole
        //SetList::NAMED_ARGS,
        SetList::PRIVATIZATION,
        SetList::TYPE_DECLARATION,
    ])
    ->withRules([
        TypedPropertyFromAssignsRector::class,
        CoversAnnotationWithValueToAttributeRector::class
    ])
    ->withImportNames();
