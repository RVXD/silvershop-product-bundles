<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Tests;

use SilverShop\Model\Order;
use SilverShop\Model\Product\OrderItem as ProductOrderItem;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverStripe\Dev\SapphireTest;

class BundlePricingTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures/bundles.yml';

    private function bundle(): BundleProduct
    {
        // Fixture "bundle": BasePrice 12.00, contents 2x Component A (10.00) + 1x Component B (5.00)
        // => components total 25.00.
        return $this->objFromFixture(BundleProduct::class, 'bundle');
    }

    public function testFixedPricingUsesBasePrice(): void
    {
        $bundle = $this->bundle();
        $bundle->PricingType = 'Fixed';

        $this->assertSame(12.0, $bundle->sellingPrice());
    }

    public function testSumPricingEqualsComponentTotal(): void
    {
        $bundle = $this->bundle();
        $bundle->PricingType = 'Sum';

        $this->assertSame(25.0, $bundle->sellingPrice());
        $this->assertSame(25.0, $bundle->getComponentsPrice());
    }

    public function testSumWithPercentageDiscount(): void
    {
        $bundle = $this->bundle();
        $bundle->PricingType = 'Sum';
        $bundle->DiscountType = 'Percentage';
        $bundle->DiscountValue = 10;

        $this->assertSame(22.5, $bundle->sellingPrice());
    }

    public function testSumWithAmountDiscount(): void
    {
        $bundle = $this->bundle();
        $bundle->PricingType = 'Sum';
        $bundle->DiscountType = 'Amount';
        $bundle->DiscountValue = 5;

        $this->assertSame(20.0, $bundle->sellingPrice());
    }

    public function testSavingsAgainstFixedPrice(): void
    {
        $bundle = $this->bundle();
        $bundle->PricingType = 'Fixed'; // 12.00 vs 25.00 of components

        $this->assertTrue($bundle->HasSavings());
        $this->assertSame(13.0, $bundle->getSavings());
        $this->assertSame(52, $bundle->getSavingsPercentage());
    }

    public function testNoSavingsForUndiscountedSum(): void
    {
        $bundle = $this->bundle();
        $bundle->PricingType = 'Sum'; // equals the component total

        $this->assertFalse($bundle->HasSavings());
        $this->assertSame(0.0, $bundle->getSavings());
        $this->assertSame(0, $bundle->getSavingsPercentage());
    }

    public function testOrderItemUsesBundleSellingPrice(): void
    {
        $bundle = $this->bundle();
        $bundle->PricingType = 'Sum';
        $bundle->write();

        // UnitPrice() recomputes from the buyable only while the order is a cart.
        $order = Order::create();
        $order->Status = 'Cart';
        $order->write();

        $item = ProductOrderItem::create();
        $item->ProductID = $bundle->ID;
        $item->ProductVersion = $bundle->Version;
        $item->Quantity = 1;
        $item->OrderID = $order->ID;
        $item->write();

        $this->assertSame(25.0, (float) $item->UnitPrice());
    }
}
