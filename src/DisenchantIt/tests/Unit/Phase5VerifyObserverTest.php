<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Reporting\PhpLintObserver;
use ItPack\DisenchantIt\Contract\Reporting\VerificationObserverInterface;
use ItPack\DisenchantIt\Contract\SemConv\SemConv;
use ItPack\DisenchantIt\Exception\SemConvCollisionException;
use ItPack\DisenchantIt\Exception\VerificationException;
use ItPack\DisenchantIt\Factory\DisenchanterFactory;

final class Phase5VerifyObserverTest extends Unit
{
    public function testObserverIsInvokedAfterRewrite(): void
    {
        $observer = new class () implements VerificationObserverInterface {
            public bool $called = false;

            public function afterRewrite(string $rewrittenSource): void
            {
                $this->called = true;
            }
        };

        $disenchanter = DisenchanterFactory::create(observer: $observer)->build();

        $code = <<<'PHP'
            <?php
            class C {
                public function a(): string { return 'admin-user'; }
                public function b(): string { return 'admin-user'; }
            }
            PHP;

        $disenchanter->rewrite($code);

        $this->assertTrue($observer->called, 'observer invoked after successful rewrite');
    }

    public function testLintFailureIsReported(): void
    {
        // A fake "php" that always exits non-zero with lint-like output.
        $script = sys_get_temp_dir() . '/fake_php_' . uniqid('', true) . '.sh';
        file_put_contents($script, "#!/bin/sh\necho \"PHP Fatal error: syntax error\"\nexit 1\n");
        chmod($script, 0o755);

        $observer = new PhpLintObserver($script);
        $disenchanter = DisenchanterFactory::create(observer: $observer)->build();

        $code = <<<'PHP'
            <?php
            class C {
                public function a(): string { return 'admin-user'; }
                public function b(): string { return 'admin-user'; }
            }
            PHP;

        $this->expectException(VerificationException::class);

        try {
            $disenchanter->rewrite($code);
        } finally {
            if (is_file($script)) {
                unlink($script);
            }
        }
    }

    public function testPhpLintObserverPassesValidSource(): void
    {
        $observer = new PhpLintObserver();
        $observer->afterRewrite("<?php\nclass C { public function a(): string { return 'x'; } }\n");

        $this->expectNotToPerformAssertions();
    }

    public function testOriginalExceptionPreservedWhenRewriteFailsBeforeObserver(): void
    {
        // A colliding SemConv name forces a business exception during rewriteWithSemConv,
        // before the observer is ever reached — so the observer must not replace it.
        $observer = new class () implements VerificationObserverInterface {
            public bool $called = false;

            public function afterRewrite(string $rewrittenSource): void
            {
                $this->called = true;
            }
        };

        $disenchanter = DisenchanterFactory::create(observer: $observer)->build();

        $code = <<<'PHP'
            <?php
            namespace App;
            class C {
                public function a(): string { return 'admin-user'; }
                public function b(): string { return 'admin-user'; }
            }
            PHP;

        // Pre-existing SemConv registry with a COLLIDING name/value under a
        // different value → SemConvCollisionException (a business exception).
        $existingSemConv = "<?php\nnamespace Generated;\ninterface SemConv {\n"
            . "    public const string ADMIN_USER = 'DIFFERENT';\n}\n";

        $this->expectException(\ItPack\DisenchantIt\Exception\SemConvCollisionException::class);
        $disenchanter->rewriteWithSemConv($code, 1, $existingSemConv);

        $this->assertFalse($observer->called, 'observer must not run when rewrite failed');
    }
}
