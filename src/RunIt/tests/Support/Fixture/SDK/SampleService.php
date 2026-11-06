<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\SDK;

use ItPack\RunIt\SDK\Attribute\ExecutedAs;

class SampleService
{
    #[ExecutedAs(name: 'service.run', kind: 'operation')]
    public function run(): void
    {
    }

    #[ExecutedAs(name: 'service.static', kind: 'task')]
    public static function statically(): void
    {
    }

    public function plain(): void
    {
    }
}
