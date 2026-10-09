<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Extension;

use SilverShop\Model\Order;
use SilverShop\Model\Product\OrderItem as ProductOrderItem;
use SilverShop\ProductBundles\Model\BundleOrderItem;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverStripe\Core\Extension;

/**
 * Restores bundled component stock when an order is cancelled — the required items plus any selected optional
 * extras — mirroring silvershop/stock's own restock of the primary line product for the same transitions.
 *
 * @extends Extension<Order>
 */
class BundleOrderExtension extends Extension
{
    public function onStatusChange(string $fromStatus, string $toStatus): void
    {
        if (!in_array($toStatus, ['AdminCancelled', 'MemberCancelled'], true)) {
            return;
        }

        foreach ($this->owner->Items() as $orderItem) {
            if (!$orderItem instanceof ProductOrderItem) {
                continue;
            }

            if ((int) $orderItem->getField('ProductVariationID') > 0) {
                continue;
            }

            $product = $orderItem->Product();
            if (!$product instanceof BundleProduct) {
                continue;
            }

            $lineQuantity = (int) $orderItem->Quantity;

            $items = $orderItem instanceof BundleOrderItem ? $orderItem->FulfilmentItems() : $product->RequiredItems();

            foreach ($items as $item) {
                $component = $item->Buyable();
                if (!$component || !$component->hasMethod('incrementStock')) {
                    continue;
                }

                $proxy = ProductOrderItem::create();
                $proxy->Quantity = $lineQuantity * max(1, (int) $item->Quantity);
                $component->incrementStock($proxy);
            }
        }
    }
}
