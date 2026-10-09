<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Tests;

use SilverShop\Model\Order;
use SilverShop\Page\Product;
use SilverShop\ProductBundles\Model\BundleItem;
use SilverShop\ProductBundles\Model\BundleOrderItem;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverShop\Stock\Model\ProductWarehouse;
use SilverShop\Stock\Model\ProductWarehouseStock;
use SilverStripe\Dev\SapphireTest;

class BundleOptionalsTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures/bundles.yml';

    private function bundle(): BundleProduct
    {
        // "bundle": required 2x A (10) + 1x B (5) => base 25; optional 1x C (8).
        return $this->objFromFixture(BundleProduct::class, 'bundle');
    }

    private function optionalC(): BundleItem
    {
        return $this->objFromFixture(BundleItem::class, 'item_c');
    }

    private function setStock(Product $product, int $quantity): void
    {
        $stock = ProductWarehouseStock::create();
        $stock->WarehouseID = $this->objFromFixture(ProductWarehouse::class, 'main')->ID;
        $stock->ProductID = $product->ID;
        $stock->ProductClass = $product->ClassName;
        $stock->Quantity = (string) $quantity;
        $stock->write();
    }

    private function stockLevel(Product $product): int
    {
        $records = ProductWarehouseStock::get()->filter([
            'ProductID' => $product->ID,
            'ProductClass' => $product->ClassName,
        ]);

        $total = 0;
        foreach ($records as $stock) {
            $total += (int) $stock->Quantity;
        }

        return $total;
    }

    private function cartLine(BundleProduct $bundle, array $optionalIDs = []): BundleOrderItem
    {
        $order = Order::create();
        $order->Status = 'Cart';
        $order->write();

        $line = BundleOrderItem::create();
        $line->ProductID = $bundle->ID;
        $line->ProductVersion = $bundle->Version;
        $line->OptionalItemIDs = $optionalIDs;
        $line->Quantity = 1;
        $line->OrderID = $order->ID;
        $line->write();

        return $line;
    }

    public function testRequiredAndOptionalSplit(): void
    {
        $bundle = $this->bundle();

        $this->assertSame(2, $bundle->RequiredItems()->count());
        $this->assertSame(1, $bundle->OptionalItems()->count());
    }

    public function testBasePriceExcludesOptionals(): void
    {
        $bundle = $this->bundle();
        $bundle->PricingType = 'Sum';

        $this->assertSame(25.0, $bundle->sellingPrice());
        $this->assertSame(8.0, $bundle->getOptionalsTotal());
    }

    public function testOptionalItemIDsSetterNormalises(): void
    {
        $line = BundleOrderItem::create();
        $line->OptionalItemIDs = [3, 1, 2, 2, 0, ''];

        $this->assertSame('1,2,3', $line->OptionalItemIDs);
    }

    public function testSelectedOptionalItemsAndFulfilment(): void
    {
        $bundle = $this->bundle();
        $bundle->write();

        $line = $this->cartLine($bundle, [$this->optionalC()->ID]);

        $this->assertSame(1, $line->SelectedOptionalItems()->count());
        $this->assertSame(3, $line->FulfilmentItems()->count()); // 2 required + 1 selected optional
    }

    public function testOrderItemPriceIncludesSelectedOptional(): void
    {
        $bundle = $this->bundle();
        $bundle->PricingType = 'Sum';
        $bundle->write();

        $line = $this->cartLine($bundle, [$this->optionalC()->ID]);

        $this->assertSame(33.0, (float) $line->UnitPrice()); // 25 base + 8 optional
    }

    public function testOrderItemPriceWithoutOptionalIsBase(): void
    {
        $bundle = $this->bundle();
        $bundle->PricingType = 'Sum';
        $bundle->write();

        $line = $this->cartLine($bundle, []);

        $this->assertSame(25.0, (float) $line->UnitPrice());
    }

    public function testStockDecrementIncludesSelectedOptionalOnly(): void
    {
        $bundle = $this->bundle();
        $bundle->write();
        $a = $this->objFromFixture(Product::class, 'component_a');
        $b = $this->objFromFixture(Product::class, 'component_b');
        $c = $this->objFromFixture(Product::class, 'component_c');

        $this->setStock($a, 10);
        $this->setStock($b, 8);
        $this->setStock($c, 5);

        $this->cartLine($bundle, [$this->optionalC()->ID])->onPlacement();

        $this->assertSame(8, $this->stockLevel($a)); // 10 - 2
        $this->assertSame(7, $this->stockLevel($b)); // 8 - 1
        $this->assertSame(4, $this->stockLevel($c)); // 5 - 1 (selected)
    }

    public function testStockUnaffectedForUnselectedOptional(): void
    {
        $bundle = $this->bundle();
        $bundle->write();
        $c = $this->objFromFixture(Product::class, 'component_c');
        $this->setStock($c, 5);

        $this->cartLine($bundle, [])->onPlacement();

        $this->assertSame(5, $this->stockLevel($c)); // untouched
    }

    public function testCanPurchaseIgnoresOptionalStock(): void
    {
        $bundle = $this->bundle();
        $c = $this->objFromFixture(Product::class, 'component_c');

        // Optional component out of stock must not block the bundle.
        $this->setStock($c, 0);

        $this->assertTrue($bundle->canPurchase());
    }

    public function testUniquedataDiffersBySelection(): void
    {
        $bundle = $this->bundle();

        $withExtra = BundleOrderItem::create();
        $withExtra->ProductID = $bundle->ID;
        $withExtra->OptionalItemIDs = [$this->optionalC()->ID];

        $withoutExtra = BundleOrderItem::create();
        $withoutExtra->ProductID = $bundle->ID;
        $withoutExtra->OptionalItemIDs = [];

        $this->assertNotSame(
            $withExtra->uniquedata()['OptionalItemIDs'],
            $withoutExtra->uniquedata()['OptionalItemIDs']
        );
    }
}
