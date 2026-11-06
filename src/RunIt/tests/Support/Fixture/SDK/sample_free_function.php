<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\SDK;

use ItPack\RunIt\SDK\Attribute\ExecutedAs;

#[ExecutedAs(name: 'free.fn', kind: 'task')]
function sample_free_function(): void
{
}
