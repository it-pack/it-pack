<?php

declare(strict_types=1);

// Run from this directory: php direct.php leak|allowed
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/tests/_bootstrap.php';

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Test\Target\Target;
use SebastianBergmann\CodeCoverage\Test\Target\TargetCollection;
use SebastianBergmann\CodeCoverage\Test\TestSize;
use SebastianBergmann\CodeCoverage\UnintentionallyCoveredCodeException;

if (!isset($argc, $argv) || $argc !== 2 || !in_array($argv[1], ['leak', 'allowed'], true)) {
    fwrite(STDERR, "Usage: php direct.php leak|allowed\n");
    exit(2);
}

$filter = new Filter();
$filter->includeFiles([__DIR__ . '/production/Target.php', __DIR__ . '/production/Collaborator.php']);
$selector = new Selector();
$coverage = new CodeCoverage($selector->select($filter), $filter);
$coverage->enableCheckForUnintentionallyCoveredCode();
$coverage->start('synthetic', TestSize::Small);
$result = AttributionCollaborator::execute();
$covers = TargetCollection::fromArray([Target::forClass(AttributionTarget::class)]);
$uses = TargetCollection::fromArray($argv[1] === 'allowed' ? [Target::forClass(AttributionCollaborator::class)] : []);
try {
    $coverage->stop(true, null, $covers, $uses);
    fwrite(STDOUT, "Direct control accepted {$argv[1]} (result $result).\n");
} catch (UnintentionallyCoveredCodeException $unintentionallyCoveredCodeException) {
    fwrite(STDERR, "Direct control rejected {$argv[1]}: " . $unintentionallyCoveredCodeException->getMessage() . "\n");
    exit(1);
}
