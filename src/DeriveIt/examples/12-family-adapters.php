<?php

declare(strict_types=1);

/*
 * 12 — A whole family of adapters, auto-wired.
 *
 * deriveFamilyAdapter() derives every member's interface AND a delegating adapter,
 * then auto-wires cross references: a method that returns another member's type is
 * wrapped in that member's adapter automatically. One call, one self-contained file.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;

$source = DeriveIt::deriveFamilyAdapter([
    _Fixture\Node::class,
    _Fixture\Tree::class,
]);

show(
    'Family of adapters: Node + Tree',
    $source,
    'TreeAdapter::getRoot(): NodeInterface { return new NodeAdapter($this->adaptee->getRoot()); } '
    . '— the cross reference is wrapped for you. NodeInterface and TreeInterface are emitted too.',
);

// Load the emitted source and confirm every generated type is valid, loadable PHP.
$tmp = sys_get_temp_dir() . '/deriveit_family_' . md5($source) . '.php';
file_put_contents($tmp, $source);
require $tmp;

foreach (['NodeInterface', 'NodeAdapter', 'TreeInterface', 'TreeAdapter'] as $short) {
    $fqcn = '_Fixture\\' . $short;
    $exists = interface_exists($fqcn, false) || class_exists($fqcn, false);
    echo "\033[1;33m{$short}:\033[0m " . ($exists ? 'present' : 'MISSING') . "\n";
}
