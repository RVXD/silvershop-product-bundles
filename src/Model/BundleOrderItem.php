<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Model;

use SilverShop\Model\Product\OrderItem as ProductOrderItem;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\SS_List;

/**
 * The order item for a {@link BundleProduct}. On top of a normal product line it records which selectable
 * items the customer chose — optional extras and mix-and-match choice-group picks — as a sorted,
 * comma-separated list of {@link BundleItem} IDs, so that:
 *
 *  - different selections are distinct cart lines (`required_fields` → cart matching),
 *  - the unit price includes the chosen items, and
 *  - stock is decremented/restored for the required items plus the chosen items.
 *
 * @property string $SelectedItemIDs
 */
class BundleOrderItem extends ProductOrderItem
{
    private static string $table_name = 'SilverShop_BundleOrderItem';

    /**
     * @var array<string, string>
     */
    private static array $db = [
        'SelectedItemIDs' => 'Varchar(255)',
    ];

    /**
     * Distinct selections are distinct cart lines (read by ShoppingCart::findLineItem via
     * `BundleOrderItem::config()->required_fields`). Stored NULL when empty so plain bundles still merge.
     *
     * @var array<string>
     */
    private static array $required_fields = [
        'SelectedItemIDs',
    ];

    /**
     * Accept an array or a string; store a sorted, comma-separated list of unique integer IDs (or NULL when
     * empty), so two identical selections always compare equal regardless of order.
     *
     * @param array<int|string>|string|null $value
     */
    public function setSelectedItemIDs($value): void
    {
        $ids = is_array($value) ? $value : explode(',', (string) $value);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        sort($ids);
        $this->setField('SelectedItemIDs', $ids === [] ? null : implode(',', $ids));
    }

    /**
     * @return array<int>
     */
    public function SelectedIDs(): array
    {
        $raw = (string) $this->getField('SelectedItemIDs');
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    /**
     * The bundle this line is for, read by its current (draft) record. Fulfilment and selection use the
     * current bundle configuration, and this resolves in every context — unlike the versioned
     * {@see Product()} lookup, which is null for an unpublished bundle mid-placement.
     */
    private function bundleRecord(): ?BundleProduct
    {
        return $this->ProductID ? BundleProduct::get()->byID($this->ProductID) : null;
    }

    /**
     * The chosen selectable items (optional extras and choice-group picks) that belong to this bundle;
     * stray or foreign IDs are ignored.
     */
    public function SelectedItems(): SS_List
    {
        $ids = $this->SelectedIDs();
        $bundle = $this->bundleRecord();
        if ($ids === [] || !$bundle instanceof BundleProduct) {
            return ArrayList::create();
        }

        return $bundle->SelectableItems()->filter('ID', $ids);
    }

    /**
     * Required items (always) plus the chosen selectable items — what this line prices and fulfils.
     */
    public function FulfilmentItems(): SS_List
    {
        $list = ArrayList::create();
        $bundle = $this->bundleRecord();
        if (!$bundle instanceof BundleProduct) {
            return $list;
        }

        foreach ($bundle->RequiredItems() as $item) {
            $list->push($item);
        }
        foreach ($this->SelectedItems() as $item) {
            $list->push($item);
        }

        return $list;
    }

    /**
     * Unit price = the bundle's base selling price (required items) plus each chosen selectable item.
     * Recomputed from the buyable only while the order is a cart, mirroring the core OrderItem.
     */
    public function UnitPrice()
    {
        $order = $this->Order();
        if ($order && $order->exists() && $order->IsCart()) {
            $buyable = $this->Buyable() ?: $this->bundleRecord();
            $price = $buyable ? $buyable->sellingPrice() : $this->UnitPrice;

            foreach ($this->SelectedItems() as $item) {
                $price += $item->getSubTotal();
            }

            $this->extend('updateUnitPrice', $price);

            return $this->UnitPrice = $price;
        }

        return $this->UnitPrice;
    }

    /**
     * Carry the selection into the cart add/remove/quantity links so they target the right line.
     *
     * @return array<string, mixed>
     */
    public function uniquedata(): array
    {
        $data = parent::uniquedata();
        $data['SelectedItemIDs'] = (string) $this->getField('SelectedItemIDs');

        return $data;
    }
}
