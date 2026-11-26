<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\AnnotationsToAttributes\Rector\Class_\CoversAnnotationWithValueToAttributeRector;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/examples',
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/tools',
    ])
    // uncomment to reach your current PHP version
    // ->withPhpSets()
    //->withTypeCoverageLevel(0)
    //->withDeadCodeLevel(0)
    //->withCodeQualityLevel(0)
    ->withSkipPath(
        __DIR__ . '/tests/Support/_generated',
    )
    ->withPhpSets(php85: true)
    ->withSets([
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
