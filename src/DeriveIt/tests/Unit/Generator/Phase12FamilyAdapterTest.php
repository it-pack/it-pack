<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Codeception\Test\Unit;
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;

class Phase12FamilyAdapterTest extends Unit
{
    private static array $loaded = [];

    private function dynamicClass(string $fqcn): string
    {
        return $fqcn;
    }

    private function load(string $source): void
    {
        $hash = md5($source);
        if (isset(self::$loaded[$hash])) {
            return;
        }

        $dir = sys_get_temp_dir() . '/deriveit_family_adapter';
        @mkdir($dir);
        $file = $dir . '/' . $hash . '.php';
        file_put_contents($file, $source);

        require $file;
        self::$loaded[$hash] = true;
    }

    public function testFamilyAdapterSourceDeclaresEveryMemberInterfaceAndAdapter(): void
    {
        $source = DeriveIt::deriveFamilyAdapter([\_Fixture\FamilyOrder::class, \_Fixture\FamilyLineItem::class]);

        verify($source)->stringContainsString('interface FamilyOrderInterface');
        verify($source)->stringContainsString('interface FamilyLineItemInterface');
        verify($source)->stringContainsString('class FamilyOrderAdapter');
        verify($source)->stringContainsString('class FamilyLineItemAdapter');
        verify($source)->stringContainsString('implements FamilyOrderInterface');
        verify($source)->stringContainsString('implements FamilyLineItemInterface');
    }

    public function testFamilyAdapterAutoWiresCrossMemberReturns(): void
    {
        $source = DeriveIt::deriveFamilyAdapter([\_Fixture\FamilyOrder::class, \_Fixture\FamilyLineItem::class]);

        // Order::lineItem(): FamilyLineItem is rewritten to FamilyLineItemInterface
        // and wrapped in the generated FamilyLineItemAdapter without any user map.
        verify($source)->stringContainsString(
            'return new \\_Fixture\\FamilyLineItemAdapter($this->adaptee->lineItem());',
        );
        verify($source)->stringContainsString(
            'return $value === null ? null : new \\_Fixture\\FamilyLineItemAdapter($value);',
        );

        $this->load($source);

        $orderAdapter = $this->dynamicClass('\_Fixture\FamilyOrderAdapter');
        $orderInterface = $this->dynamicClass('\_Fixture\FamilyOrderInterface');
        $lineItemAdapter = $this->dynamicClass('\_Fixture\FamilyLineItemAdapter');

        $adapter = new $orderAdapter(new \_Fixture\FamilyOrder());

        verify($adapter)->instanceOf($orderInterface);
        verify($adapter->id())->equals(7);

        $item = $adapter->lineItem();
        verify($item)->instanceOf($lineItemAdapter);
        verify($item->total())->equals(100);

        verify($adapter->maybeLineItem())->null();
    }

    public function testFamilyAdapterHonorsUserWrapperMapOverride(): void
    {
        $source = DeriveIt::deriveFamilyAdapter(
            [\_Fixture\FamilyOrder::class, \_Fixture\FamilyLineItem::class],
            ['_Fixture\FamilyLineItemInterface' => '_Fixture\FamilyLineItemAdapter'],
        );

        // The explicit map merges over the auto map; wiring is still present.
        verify($source)->stringContainsString(
            'return new \\_Fixture\\FamilyLineItemAdapter($this->adaptee->lineItem());',
        );
    }

    public function testFamilyAdapterAcceptsConfig(): void
    {
        $source = DeriveIt::deriveFamilyAdapter(
            [\_Fixture\FamilyOrder::class, \_Fixture\FamilyLineItem::class],
            [],
            new DeriveConfig(includeStatic: true),
        );

        verify($source)->stringContainsString('class FamilyOrderAdapter');
        verify($source)->stringContainsString('class FamilyLineItemAdapter');
    }
}
