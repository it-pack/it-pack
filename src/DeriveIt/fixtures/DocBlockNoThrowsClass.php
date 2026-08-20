<?php

declare(strict_types=1);

namespace _Fixture;

class DocBlockNoThrowsClass
{
    /**
     * Just a regular docblock with no exception tags.
     *
     * @return void
     */
    public function run(): void
    {
    }
}
