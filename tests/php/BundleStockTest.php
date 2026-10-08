<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Tests;

use SilverShop\Model\Order;
use SilverShop\Model\Product\OrderItem as ProductOrderItem;
use SilverShop\Page\Product;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverShop\Stock\Model\ProductWarehouse;
use SilverShop\Stock\Model\ProductWarehouseStock;
use SilverStripe\Dev\SapphireTest;

class BundleStockTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures/bundles.yml';

    private function setStock(Product $product, int $quantity): void
    {
        $warehouse = $this->objFromFixture(ProductWarehouse::class, 'main');
        $stock = ProductWarehouseStock::create();
        $stock->WarehouseID = $warehouse->ID;
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

    public function testOnPlacementDecrementsComponentStockByQuantity(): void
    {
        $bundle = $this->objFromFixture(BundleProduct::class, 'bundle');
        $componentA = $this->objFromFixture(Product::class, 'component_a');
        $componentB = $this->objFromFixture(Product::class, 'component_b');

        $this->setStock($componentA, 10);
        $this->setStock($componentB, 8);

        $item = ProductOrderItem::create();
        $item->ProductID = $bundle->ID;
        $item->ProductVersion = $bundle->Version;
        $item->Quantity = 3;
        $item->write();
        $item->onPlacement();

        // The bundle contains 2x A and 1x B; ordering 3 bundles removes 6x A and 3x B.
        $this->assertSame(4, $this->stockLevel($componentA));
        $this->assertSame(5, $this->stockLevel($componentB));
    }

    public function testCancellationRestocksComponents(): void
    {
        $bundle = $this->objFromFixture(BundleProduct::class, 'bundle');
        $componentA = $this->objFromFixture(Product::class, 'component_a');
        $componentB = $this->objFromFixture(Product::class, 'component_b');

        // Start as if 3 bundles were already placed (A: 10-6=4, B: 8-3=5).
        $this->setStock($componentA, 4);
        $this->setStock($componentB, 5);

        $order = Order::create();
        $order->write();

        $item = ProductOrderItem::create();
        $item->ProductID = $bundle->ID;
        $item->ProductVersion = $bundle->Version;
        $item->Quantity = 3;
        $item->OrderID = $order->ID;
        $item->write();

        $fromStatus = 'Paid';
        $toStatus = 'AdminCancelled';
        $order->extend('onStatusChange', $fromStatus, $toStatus);

        $this->assertSame(10, $this->stockLevel($componentA));
        $this->assertSame(8, $this->stockLevel($componentB));
    }

    public function testBundleNotPurchasableWhenComponentOutOfStock(): void
    {
        $bundle = $this->objFromFixture(BundleProduct::class, 'bundle');
        $componentA = $this->objFromFixture(Product::class, 'component_a');

        // A zero, non-unlimited stock record makes component A unavailable, so the bundle is too.
        $this->setStock($componentA, 0);

        $this->assertFalse($bundle->canPurchase());
    }
}
