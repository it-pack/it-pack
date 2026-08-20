<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;

class CompositeReader implements ConfigurationReaderInterface
{
    /**
     * @param array<ConfigurationReaderInterface> $readers
     */
    public function __construct(private array $readers)
    {

    }

    public function read(ConfigurationResourceInterface $resource): string
    {
        foreach ($this->readers as $reader) {
            if ($reader->supports($resource)) {
                return $reader->read($resource);
            }
        }

        throw new \InvalidArgumentException('No reader supports the given resource of type "'.$resource->format()->value.'".');
    }

    public function supports(ConfigurationResourceInterface $resource): bool
    {
        return array_any($this->readers, fn ($reader) => $reader->supports($resource));
    }
}
