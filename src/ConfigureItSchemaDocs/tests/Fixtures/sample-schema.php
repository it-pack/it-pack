<?php

declare(strict_types=1);

use Nette\Schema\Expect;

/*
 * Sample `Nette\Schema\Expect` definition used by the schema-docs test suite.
 *
 * Exercises every branch the introspector must handle: nested structures,
 * scalar types, ranges, patterns, nullability, defaults, required flags,
 * deprecation, enumerations (scalar anyOf), unions (schema anyOf), lists and
 * associative arrays.
 *
 * @return \Nette\Schema\Schema
 */
return Expect::structure([
    'app' => Expect::structure([
        'name' => Expect::string()->default('configure-it'),
        'debug' => Expect::bool()->default(false),
        'port' => Expect::int()->min(1)->max(65535)->default(8080),
        'secret' => Expect::string()->nullable(),
        'env' => Expect::anyOf('dev', 'staging', 'prod')->default('dev'),
        'tags' => Expect::listOf(Expect::string())->default([]),
        'features' => Expect::arrayOf(Expect::bool())->default([]),
        'source' => Expect::anyOf(
            Expect::string(),
            Expect::structure(['url' => Expect::string()]),
        ),
        'deprecatedFlag' => Expect::bool()->deprecated('use another flag'),
        'email' => Expect::string()->pattern('/^.+@.+$/')->required(),
    ])->deprecated('use the new app block'),
]);
