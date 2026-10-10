<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Tests;

use SilverShop\ProductBundles\BundleSalesReport;
use SilverStripe\Dev\SapphireTest;

class BundleSalesReportTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static bool $use_draft_site = true;

    /**
     * The report instantiates, has a title, and its sourceRecords() executes cleanly on an empty DB — which
     * exercises the hand-written multi-join SQLSelect (OrderAttribute/OrderItem/Product_OrderItem/Order/
     * SiteTree/BundleProduct) against the test database (SQLite in CI).
     */
    public function testReportRunsCleanly(): void
    {
        $report = new BundleSalesReport();

        $this->assertNotEmpty((string) $report->title(), 'report has a title');
        $this->assertCount(0, $report->sourceRecords(), 'report runs cleanly with no data');
    }
}
