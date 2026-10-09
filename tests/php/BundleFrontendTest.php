<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Tests;

use SilverShop\Page\Product;
use SilverShop\ProductBundles\Model\BundleItem;
use SilverShop\ProductBundles\Model\BundleOrderItem;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverStripe\Dev\SapphireTest;

class BundleFrontendTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures/bundles.yml';

    public function testBundleItemSubTotal(): void
    {
        $itemA = $this->objFromFixture(BundleItem::class, 'item_a');
        $itemB = $this->objFromFixture(BundleItem::class, 'item_b');

        $this->assertSame(20.0, $itemA->getSubTotal()); // 10.00 x2
        $this->assertSame(5.0, $itemB->getSubTotal());  // 5.00 x1
    }

    public function testBundleContentsTemplateRendersComponentsAndSavings(): void
    {
        $bundle = $this->objFromFixture(BundleProduct::class, 'bundle');
        $bundle->PricingType = 'Fixed'; // 12.00 vs 25.00 of components => 52% saving

        $html = (string) $bundle->renderWith(['type' => 'Includes', 'SilverShop\\ProductBundles\\BundleContents']);

        $this->assertStringContainsString('Component A', $html);
        $this->assertStringContainsString('Component B', $html);
        $this->assertStringContainsString('bundle-contents', $html);
        $this->assertStringContainsString('Save', $html);
        $this->assertStringContainsString('52', $html);
    }

    public function testPlainProductRendersNoBundleSection(): void
    {
        $product = $this->objFromFixture(Product::class, 'component_a');

        $html = (string) $product->renderWith(['type' => 'Includes', 'SilverShop\\ProductBundles\\BundleContents']);

        $this->assertStringNotContainsString('bundle-contents', $html);
    }

    public function testCartSummaryRendersSelectedContents(): void
    {
        $bundle = $this->objFromFixture(BundleProduct::class, 'bundle');
        $bundle->write();
        $itemC = $this->objFromFixture(BundleItem::class, 'item_c');

        $line = BundleOrderItem::create();
        $line->ProductID = $bundle->ID;
        $line->ProductVersion = $bundle->Version;
        $line->OptionalItemIDs = [$itemC->ID];
        $line->write();

        $html = (string) $line->renderWith(['type' => 'Includes', 'SilverShop\\ProductBundles\\BundleCartSummary']);

        $this->assertStringContainsString('Component A', $html); // required
        $this->assertStringContainsString('Component B', $html); // required
        $this->assertStringContainsString('Component C', $html); // selected optional
    }
}
