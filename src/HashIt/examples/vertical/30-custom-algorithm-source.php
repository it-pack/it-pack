<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\AlgorithmSource\CompositeAlgorithmSource;
use ItPack\HashIt\AlgorithmSource\NativeAlgorithmSource;
use ItPack\HashIt\Contract\AlgorithmSourceInterface;
use ItPack\HashIt\HashAlgorithm;

banner('Custom algorithm catalogue (pluggable AlgorithmSourceInterface)');

// A policy source that only admits recommended algorithms.
final class RecommendedOnlySource implements AlgorithmSourceInterface
{
    public function algorithms(): array
    {
        return HashAlgorithm::recommendedAlgorithms();
    }

    public function supports(string $id): bool
    {
        return $this->match($id) !== null;
    }

    public function resolve(string $id): HashAlgorithm
    {
        $match = $this->match($id);
        if ($match === null) {
            throw new \InvalidArgumentException('Algorithm not allowed by policy: ' . $id);
        }

        return $match;
    }

    private function match(string $id): ?HashAlgorithm
    {
        foreach (HashAlgorithm::recommendedAlgorithms() as $case) {
            if ($case->value === $id || strtolower($case->name) === strtolower($id)) {
                return $case;
            }
        }

        return null;
    }
}

$hasher = NativeHashIt::create(source: new RecommendedOnlySource());
echo 'policy catalogue size: ' . count($hasher->algorithms()) . "\n";
echo "supports('sha256')? " . ($hasher->supports('sha256') ? 'yes' : 'no') . "\n";
echo "supports('md5')?    " . ($hasher->supports('md5') ? 'yes' : 'no') . "\n";

try {
    $hasher->resolve('md5');
} catch (\InvalidArgumentException $e) {
    echo 'resolve(\'md5\') blocked by policy: ' . $e->getMessage() . "\n";
}

echo 'hash with allowed algo: ' . $hasher->hash('data', 'sha3-256')->value() . "\n";

// Compose sources: first match wins; de-duplicated by value.
$combo = new CompositeAlgorithmSource([new RecommendedOnlySource(), new NativeAlgorithmSource()]);
$comboHasher = NativeHashIt::create(source: $combo);
echo "\ncomposed supports('sha256')? " . ($comboHasher->supports('sha256') ? 'yes' : 'no') . "\n";
echo "composed supports('md5')?    " . ($comboHasher->supports('md5') ? 'yes' : 'no')
    . " (native source still knows md5)\n";

echo "\nThe catalogue port answers 'which algorithms may we use?'; resolve() is the\n";
echo "safe entry point to enforce policy before hashing.\n";
