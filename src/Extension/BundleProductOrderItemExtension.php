<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Extension;

use SilverShop\Model\OrderItem as BaseOrderItem;
use SilverShop\Model\Product\OrderItem as ProductOrderItem;
use SilverShop\ProductBundles\Model\BundleOrderItem;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverStripe\Core\Extension;

/**
 * When a bundle line is placed on an order, decrement stock on each fulfilled product — the required items
 * plus any selected optional extras — for the purchased quantity (component quantity x ordered quantity),
 * mirroring how silvershop/stock decrements the primary line product. Delegated to {@see decrementStock()}
 * when silvershop/stock is installed, and a no-op otherwise.
 *
 * @extends Extension<BaseOrderItem>
 */
class BundleProductOrderItemExtension extends Extension
{
    public function onPlacement(): void
    {
        $owner = $this->owner;

        if (!$owner instanceof ProductOrderItem) {
            return;
        }

        // A variation line manages its own stock; bundles are sold as a plain product line.
        if ((int) $owner->getField('ProductVariationID') > 0) {
            return;
        }

        $product = $owner->Product();
        if (!$product instanceof BundleProduct) {
            return;
        }

        $lineQuantity = (int) $owner->Quantity;

        $items = $owner instanceof BundleOrderItem ? $owner->FulfilmentItems() : $product->RequiredItems();

        foreach ($items as $item) {
            $component = $item->Product();
            if (!$component || !$component->exists() || !$component->hasMethod('decrementStock')) {
                continue;
            }

            $proxy = ProductOrderItem::create();
            $proxy->Quantity = $lineQuantity * max(1, (int) $item->Quantity);
            $component->decrementStock($proxy);
        }
    }
}
