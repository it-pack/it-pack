<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\ConstantNameGenerator;
use ItPack\DisenchantIt\Contract\ConstantNameGeneratorInterface;
use ItPack\DisenchantIt\Contract\DisenchanterFactoryInterface;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\Disenchanter;

/**
 * Composition root for the detection orchestrator. Pulls the scanner, filter and
 * grouper from the DetectorFactory and the name generator from its own port, so
 * the Disenchanter receives each collaborator already assembled.
 */
final readonly class DisenchanterFactory implements DisenchanterFactoryInterface
{
    public function __construct(
        private readonly DetectorFactory $detectors,
        private readonly ConstantNameGeneratorInterface $generator,
    ) {
    }

    public static function create(): self
    {
        return new self(DetectorFactory::create(), ConstantNameGenerator::create());
    }

    public function build(): DisenchanterInterface
    {
        return new Disenchanter(
            $this->detectors->build(),
            $this->detectors->buildFilter(),
            $this->detectors->buildGrouper(),
            $this->generator,
        );
    }
}
