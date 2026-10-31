<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Domain\CancellationScope;

final readonly class CascadingCancellationScopeFactory implements CancellationScopeFactoryInterface
{
    public function __construct(
        private CancellationScopeInterface $root,
    ) {
    }

    public function create(): CancellationScopeInterface
    {
        $scope = new CancellationScope();

        $this->root->onCancel(static function () use ($scope): void {
            $scope->cancel(CancellationReason::ParentCancelled);
        });

        return $scope;
    }
}
