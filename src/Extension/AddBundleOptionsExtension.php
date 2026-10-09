<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Extension;

use SilverShop\Forms\AddProductForm;
use SilverShop\ProductBundles\Model\BundleItem;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxSetField;

/**
 * Adds a checkbox for each of a bundle's optional extras to its add-to-cart form, and marks the selection
 * saveable so it is stored on the {@link \SilverShop\ProductBundles\Model\BundleOrderItem} (which prices and
 * fulfils the chosen extras, and keeps distinct selections as distinct cart lines).
 *
 * @extends Extension<AddProductForm>
 */
class AddBundleOptionsExtension extends Extension
{
    public function updateAddProductForm(): void
    {
        $buyable = $this->owner->getBuyable();
        if (!$buyable instanceof BundleProduct) {
            return;
        }

        $source = [];
        /** @var BundleItem $item */
        foreach ($buyable->OptionalItems() as $item) {
            $product = $item->Product();
            if (!$product || !$product->exists()) {
                continue;
            }

            $label = $product->Title;
            if ((int) $item->Quantity > 1) {
                $label = $item->Quantity . '× ' . $label;
            }

            $source[$item->ID] = $label . ' (+' . $item->obj('SubTotal')?->Nice() . ')';
        }

        if ($source === []) {
            return;
        }

        $this->owner->Fields()->push(
            CheckboxSetField::create(
                'OptionalItemIDs',
                _t(self::class . '.Extras', 'Optional extras'),
                $source
            )
        );

        $this->owner->setSaveableFields(['OptionalItemIDs']);
    }
}
