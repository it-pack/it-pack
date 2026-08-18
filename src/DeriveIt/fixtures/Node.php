<?php

declare(strict_types=1);

namespace _Fixture;

class Node
{
    public ?Node $next = null;

    public string $label;

    public function getNext(): ?Node
    {
        return $this->next;
    }

    public function addChild(Node $child): void
    {
    }

    public function label(): string
    {
        return $this->label;
    }
}
