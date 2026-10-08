<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Extension;

use SilverShop\Model\OrderItem as BaseOrderItem;
use SilverShop\Model\Product\OrderItem as ProductOrderItem;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverStripe\Core\Extension;

/**
 * When a bundle line is placed on an order, decrement stock on each bundled product for the purchased
 * quantity (component quantity x ordered quantity), mirroring how silvershop/stock decrements the primary
 * line product. The call is delegated to {@see decrementStock()} when silvershop/stock is installed and
 * no-ops otherwise, so the module works with or without stock management.
 *
 * @extends Extension<BaseOrderItem>
 */
class BundleProductOrderItemExtension extends Extension
{
    public function onPlacement(): void
    {
        if (!$this->owner instanceof ProductOrderItem) {
            return;
        }

        // A variation line manages its own stock; bundles are sold as a plain product line.
        if ((int) $this->owner->getField('ProductVariationID') > 0) {
            return;
        }

        $product = $this->owner->Product();
        if (!$product instanceof BundleProduct) {
            return;
        }

        $lineQuantity = (int) $this->owner->Quantity;

        foreach ($product->BundleItems() as $item) {
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
