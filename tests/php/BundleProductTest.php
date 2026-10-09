<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Tests;

use SilverShop\Page\Product;
use SilverShop\ProductBundles\Model\BundleItem;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Dev\SapphireTest;

class BundleProductTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures/bundles.yml';

    public function testEmptyBundleIsNotPurchasable(): void
    {
        $bundle = $this->objFromFixture(BundleProduct::class, 'bundle_empty');
        $this->assertFalse($bundle->canPurchase(), 'A bundle with no components must not be purchasable.');
    }

    public function testPopulatedBundleIsPurchasable(): void
    {
        $bundle = $this->objFromFixture(BundleProduct::class, 'bundle');
        $this->assertTrue($bundle->canPurchase(), 'A bundle whose components are all available must be purchasable.');
    }

    public function testBundleItemsRelation(): void
    {
        $bundle = $this->objFromFixture(BundleProduct::class, 'bundle');
        $this->assertSame(3, $bundle->BundleItems()->count());
    }

    public function testCannotNestBundles(): void
    {
        $outer = $this->objFromFixture(BundleProduct::class, 'bundle');
        $inner = $this->objFromFixture(BundleProduct::class, 'bundle_empty');

        $item = BundleItem::create();
        $item->BundleID = $outer->ID;
        $item->ProductID = $inner->ID;
        $item->Quantity = 1;

        $this->expectException(ValidationException::class);
        $item->write();
    }

    public function testQuantityMustBePositive(): void
    {
        $bundle = $this->objFromFixture(BundleProduct::class, 'bundle');
        $component = $this->objFromFixture(Product::class, 'component_a');

        $item = BundleItem::create();
        $item->BundleID = $bundle->ID;
        $item->ProductID = $component->ID;
        $item->Quantity = 0;

        $this->expectException(ValidationException::class);
        $item->write();
    }
}
