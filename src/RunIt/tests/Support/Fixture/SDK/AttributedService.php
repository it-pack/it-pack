<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\SDK;

use ItPack\RunIt\SDK\Attribute\ExecutedAs;

#[ExecutedAs(name: 'service.class', kind: 'batch')]
final class AttributedService
{
    public function handle(): void
    {
    }
}
