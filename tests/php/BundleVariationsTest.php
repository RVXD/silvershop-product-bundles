<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Tests;

use SilverShop\Model\Order;
use SilverShop\Model\Variation\Variation;
use SilverShop\Page\Product;
use SilverShop\ProductBundles\Model\BundleItem;
use SilverShop\ProductBundles\Model\BundleOrderItem;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverShop\Stock\Model\ProductWarehouse;
use SilverShop\Stock\Model\ProductWarehouseStock;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Dev\SapphireTest;

class BundleVariationsTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures/bundles.yml';

    private function makeVariation(Product $product, float $price): Variation
    {
        $variation = Variation::create();
        $variation->ProductID = $product->ID;
        $variation->Price = $price;
        $variation->InternalItemID = 'VAR-' . $product->ID;
        $variation->write();

        return $variation;
    }

    public function testBuyableResolvesToVariation(): void
    {
        $product = $this->objFromFixture(Product::class, 'component_a');
        $variation = $this->makeVariation($product, 15.00);

        $item = BundleItem::create();
        $item->ProductID = $product->ID;
        $item->VariationID = $variation->ID;

        $this->assertInstanceOf(Variation::class, $item->Buyable());
        $this->assertSame($variation->ID, $item->Buyable()->ID);
    }

    public function testBuyableFallsBackToProduct(): void
    {
        $product = $this->objFromFixture(Product::class, 'component_a');

        $item = BundleItem::create();
        $item->ProductID = $product->ID;

        $this->assertInstanceOf(Product::class, $item->Buyable());
    }

    public function testSubTotalUsesVariationPrice(): void
    {
        $product = $this->objFromFixture(Product::class, 'component_a'); // product base 10.00
        $variation = $this->makeVariation($product, 15.00);

        $item = BundleItem::create();
        $item->ProductID = $product->ID;
        $item->VariationID = $variation->ID;
        $item->Quantity = 2;

        $this->assertSame(30.0, $item->getSubTotal()); // 15.00 x 2 (variation price, not product's 10)
    }

    public function testVariationMustBelongToProduct(): void
    {
        $productA = $this->objFromFixture(Product::class, 'component_a');
        $productB = $this->objFromFixture(Product::class, 'component_b');
        $variationOfA = $this->makeVariation($productA, 12.00);

        $bundle = $this->objFromFixture(BundleProduct::class, 'bundle');

        $item = BundleItem::create();
        $item->BundleID = $bundle->ID;
        $item->ProductID = $productB->ID; // mismatched product
        $item->VariationID = $variationOfA->ID;
        $item->Quantity = 1;

        $this->expectException(ValidationException::class);
        $item->write();
    }

    public function testStockDecrementsTheVariation(): void
    {
        $product = $this->objFromFixture(Product::class, 'component_a');
        $variation = $this->makeVariation($product, 15.00);

        $bundle = BundleProduct::create();
        $bundle->Title = 'Variation Bundle';
        $bundle->AllowPurchase = true;
        $bundle->write();

        $item = BundleItem::create();
        $item->BundleID = $bundle->ID;
        $item->ProductID = $product->ID;
        $item->VariationID = $variation->ID;
        $item->Quantity = 1;
        $item->write();

        // Stock on the variation (keyed by its own ID + class).
        $stock = ProductWarehouseStock::create();
        $stock->WarehouseID = $this->objFromFixture(ProductWarehouse::class, 'main')->ID;
        $stock->ProductID = $variation->ID;
        $stock->ProductClass = $variation->ClassName;
        $stock->Quantity = '10';
        $stock->write();

        $order = Order::create();
        $order->Status = 'Cart';
        $order->write();

        $line = BundleOrderItem::create();
        $line->ProductID = $bundle->ID;
        $line->ProductVersion = $bundle->Version;
        $line->Quantity = 1;
        $line->OrderID = $order->ID;
        $line->write();
        $line->onPlacement();

        $level = (int) ProductWarehouseStock::get()
            ->filter(['ProductID' => $variation->ID, 'ProductClass' => $variation->ClassName])
            ->first()->Quantity;

        $this->assertSame(9, $level); // 10 - 1, decremented on the variation
    }
}
