<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Tests;

use SilverShop\Model\Order;
use SilverShop\Page\Product;
use SilverShop\ProductBundles\Model\BundleChoiceGroup;
use SilverShop\ProductBundles\Model\BundleItem;
use SilverShop\ProductBundles\Model\BundleOrderItem;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverShop\Stock\Model\ProductWarehouse;
use SilverShop\Stock\Model\ProductWarehouseStock;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Dev\SapphireTest;

class BundleChoiceGroupsTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures/bundles.yml';

    private function choiceBundle(): BundleProduct
    {
        // "choice_bundle": Fixed base 5.00; one group "pick exactly 2" of X/Y/Z (7.00 each).
        return $this->objFromFixture(BundleProduct::class, 'choice_bundle');
    }

    private function choiceItemIDs(): array
    {
        return [
            $this->objFromFixture(BundleItem::class, 'choice_item_x')->ID,
            $this->objFromFixture(BundleItem::class, 'choice_item_y')->ID,
            $this->objFromFixture(BundleItem::class, 'choice_item_z')->ID,
        ];
    }

    private function cartLine(BundleProduct $bundle, array $selectedIDs): BundleOrderItem
    {
        $bundle->write();

        $order = Order::create();
        $order->Status = 'Cart';
        $order->write();

        $line = BundleOrderItem::create();
        $line->ProductID = $bundle->ID;
        $line->ProductVersion = $bundle->Version;
        $line->SelectedItemIDs = $selectedIDs;
        $line->Quantity = 1;
        $line->OrderID = $order->ID;
        $line->write();

        return $line;
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

    public function testChoiceItemsExcludedFromRequiredAndOptional(): void
    {
        $bundle = $this->choiceBundle();

        $this->assertSame(0, $bundle->RequiredItems()->count());
        $this->assertSame(0, $bundle->OptionalItems()->count());
        $this->assertSame(3, $bundle->SelectableItems()->count());
        $this->assertSame(1, $bundle->ChoiceGroups()->count());
    }

    public function testChoiceRangeLabel(): void
    {
        $group = $this->objFromFixture(BundleChoiceGroup::class, 'group_pick2');
        $this->assertSame('2', $group->getChoiceRange());
    }

    public function testGroupRejectsMaxBelowMin(): void
    {
        $bundle = $this->choiceBundle();

        $group = BundleChoiceGroup::create();
        $group->BundleID = $bundle->ID;
        $group->Title = 'Bad';
        $group->MinChoices = 3;
        $group->MaxChoices = 2;

        $this->expectException(ValidationException::class);
        $group->write();
    }

    public function testValidateSelectionEnforcesMinimum(): void
    {
        $bundle = $this->choiceBundle();
        [$x, $y] = $this->choiceItemIDs();

        $this->assertFalse($bundle->validateSelection([$x])->isValid());      // only 1, need 2
        $this->assertTrue($bundle->validateSelection([$x, $y])->isValid());   // exactly 2
    }

    public function testValidateSelectionEnforcesMaximum(): void
    {
        $bundle = $this->choiceBundle();
        [$x, $y, $z] = $this->choiceItemIDs();

        $this->assertFalse($bundle->validateSelection([$x, $y, $z])->isValid()); // 3 > max 2
    }

    public function testOrderItemPricesSelectedChoices(): void
    {
        $bundle = $this->choiceBundle();
        [$x, $y] = $this->choiceItemIDs();

        $line = $this->cartLine($bundle, [$x, $y]);

        // Fixed base 5.00 + 2 chosen options at 7.00 each.
        $this->assertSame(19.0, (float) $line->UnitPrice());
        $this->assertSame(2, $line->FulfilmentItems()->count()); // no required items, 2 chosen
    }

    public function testStockDecrementsForSelectedChoicesOnly(): void
    {
        $bundle = $this->choiceBundle();
        [$x, $y] = $this->choiceItemIDs();

        $choiceX = $this->objFromFixture(Product::class, 'choice_x');
        $choiceY = $this->objFromFixture(Product::class, 'choice_y');
        $choiceZ = $this->objFromFixture(Product::class, 'choice_z');

        $this->setStock($choiceX, 5);
        $this->setStock($choiceY, 5);
        $this->setStock($choiceZ, 5);

        $this->cartLine($bundle, [$x, $y])->onPlacement();

        $this->assertSame(4, $this->stockLevel($choiceX));
        $this->assertSame(4, $this->stockLevel($choiceY));
        $this->assertSame(5, $this->stockLevel($choiceZ)); // not chosen
    }
}
