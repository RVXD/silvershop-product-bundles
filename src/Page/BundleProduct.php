<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Page;

use SilverShop\Page\Product;
use SilverShop\ProductBundles\Model\BundleItem;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Security\Member;

/**
 * A product that groups other products and sells them as a single line.
 *
 * A bundle shares the SilverShop_Product table (ClassName discriminator): it is a Product, so it has a
 * catalog page, a price and the normal cart/checkout behaviour. Its contents are modelled as
 * {@link BundleItem} rows (a has_many join object) each carrying the quantity of a component the bundle
 * contains. That join object — rather than a flat many_many of products — is the structure later features
 * (optional items, choice groups, per-item pricing, variations) extend without reshaping the bundle itself.
 *
 * Slice 1 scope: fixed price (the inherited BasePrice) plus component stock linkage via silvershop/stock.
 */
class BundleProduct extends Product
{
    private static string $table_name = 'SilverShop_BundleProduct';

    private static string $singular_name = 'Bundle';

    private static string $plural_name = 'Bundles';

    private static string $description = 'Groups other products into one purchasable bundle.';

    private static string $icon_class = 'font-icon-box';

    /**
     * @var array<string, string>
     */
    private static array $has_many = [
        'BundleItems' => BundleItem::class,
    ];

    /**
     * Bundle items are meaningless without their bundle, so remove them with it.
     *
     * @var array<string>
     */
    private static array $cascade_deletes = [
        'BundleItems',
    ];

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields): void {
            if ($this->isInDB()) {
                $fields->addFieldToTab(
                    'Root.Bundle',
                    GridField::create(
                        'BundleItems',
                        _t(self::class . '.BundleItems', 'Bundled products'),
                        $this->BundleItems(),
                        GridFieldConfig_RecordEditor::create()
                    )
                );
            } else {
                $fields->addFieldToTab('Root.Bundle', LiteralField::create(
                    'BundleItemsNotice',
                    '<p class="message notice">' . _t(
                        self::class . '.SaveFirst',
                        'Save this bundle first, then add the products it contains.'
                    ) . '</p>'
                ));
            }
        });

        return parent::getCMSFields();
    }

    /**
     * A bundle is purchasable only when it has at least one component and every component can be purchased
     * for the required quantity (the component's per-bundle quantity multiplied by the ordered quantity).
     */
    public function canPurchase(?Member $member = null, int $quantity = 1): bool
    {
        if (!parent::canPurchase($member, $quantity)) {
            return false;
        }

        $items = $this->BundleItems();
        if (!$items->exists()) {
            return false;
        }

        foreach ($items as $item) {
            $product = $item->Product();
            if (!$product || !$product->exists()) {
                return false;
            }

            $required = $quantity * max(1, (int) $item->Quantity);
            if (!$product->canPurchase($member, $required)) {
                return false;
            }
        }

        return true;
    }
}
