<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Reporting\PhpLintObserver;
use ItPack\DisenchantIt\Exception\VerificationException;

final class PhpLintObserverTest extends Unit
{
    public function testValidSourcePassesLint(): void
    {
        $observer = new PhpLintObserver();

        $observer->afterRewrite("<?php\nclass C {}\n");

        $this->expectNotToPerformAssertions();
    }

    public function testBrokenSourceThrowsVerificationException(): void
    {
        $observer = new PhpLintObserver();

        $this->expectException(VerificationException::class);
        $observer->afterRewrite("<?php\nclass { invalid syntax\n");
    }
}
