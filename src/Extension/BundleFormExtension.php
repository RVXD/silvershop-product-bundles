<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Extension;

use SilverShop\Page\Product;
use SilverShop\ProductBundles\Form\BundleAddProductForm;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverStripe\Core\Extension;

/**
 * Makes a bundle's product page use {@link BundleAddProductForm} (which renders the optional extras and
 * choice groups, and combines + validates the selection) via SilverShop's `updateFormClass` hook.
 *
 * @extends Extension<Product>
 */
class BundleFormExtension extends Extension
{
    public function updateFormClass(string &$formClass): void
    {
        if ($this->owner instanceof BundleProduct) {
            $formClass = BundleAddProductForm::class;
        }
    }
}
