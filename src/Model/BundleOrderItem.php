<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Model;

use SilverShop\Model\Product\OrderItem as ProductOrderItem;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\SS_List;

/**
 * The order item for a {@link BundleProduct}. On top of a normal product line it records which optional
 * extras the customer selected (a sorted, comma-separated list of {@link BundleItem} IDs) so that:
 *
 *  - different selections are distinct cart lines (`required_fields` → cart matching),
 *  - the unit price includes the selected extras, and
 *  - stock is decremented/restored for the required items plus the selected extras.
 *
 * @property string $OptionalItemIDs
 */
class BundleOrderItem extends ProductOrderItem
{
    private static string $table_name = 'SilverShop_BundleOrderItem';

    /**
     * @var array<string, string>
     */
    private static array $db = [
        'OptionalItemIDs' => 'Varchar(255)',
    ];

    /**
     * Distinct optional selections are distinct cart lines (read by ShoppingCart::findLineItem via
     * `BundleOrderItem::config()->required_fields`).
     *
     * @var array<string>
     */
    private static array $required_fields = [
        'OptionalItemIDs',
    ];

    /**
     * Accept an array or a string; store a sorted, comma-separated list of unique integer IDs, so two
     * selections of the same extras always compare equal regardless of order.
     *
     * @param array<int|string>|string|null $value
     */
    public function setOptionalItemIDs($value): void
    {
        $ids = is_array($value) ? $value : explode(',', (string) $value);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        sort($ids);
        $this->setField('OptionalItemIDs', implode(',', $ids));
    }

    /**
     * @return array<int>
     */
    public function SelectedOptionalIDs(): array
    {
        $raw = (string) $this->getField('OptionalItemIDs');
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
     * The selected optional items that actually belong to this bundle (stray/foreign IDs are ignored).
     */
    public function SelectedOptionalItems(): SS_List
    {
        $ids = $this->SelectedOptionalIDs();
        $bundle = $this->bundleRecord();
        if ($ids === [] || !$bundle instanceof BundleProduct) {
            return ArrayList::create();
        }

        return $bundle->OptionalItems()->filter('ID', $ids);
    }

    /**
     * Required items (always) plus the selected optional extras — what this line prices and fulfils.
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
        foreach ($this->SelectedOptionalItems() as $item) {
            $list->push($item);
        }

        return $list;
    }

    /**
     * Unit price = the bundle's base selling price (required items) plus each selected optional extra.
     * Recomputed from the buyable only while the order is a cart, mirroring the core OrderItem.
     */
    public function UnitPrice()
    {
        $order = $this->Order();
        if ($order && $order->exists() && $order->IsCart()) {
            $buyable = $this->Buyable() ?: $this->bundleRecord();
            $price = $buyable ? $buyable->sellingPrice() : $this->UnitPrice;

            foreach ($this->SelectedOptionalItems() as $item) {
                $price += $item->getSubTotal();
            }

            $this->extend('updateUnitPrice', $price);

            return $this->UnitPrice = $price;
        }

        return $this->UnitPrice;
    }

    /**
     * Carry the optional selection into the cart add/remove/quantity links so they target the right line.
     *
     * @return array<string, mixed>
     */
    public function uniquedata(): array
    {
        $data = parent::uniquedata();
        $data['OptionalItemIDs'] = (string) $this->getField('OptionalItemIDs');

        return $data;
    }
}
