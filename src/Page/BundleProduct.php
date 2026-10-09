<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Page;

use SilverShop\Model\Order;
use SilverShop\Page\Product;
use SilverShop\ProductBundles\Model\BundleItem;
use SilverShop\ProductBundles\Model\BundleOrderItem;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\HasManyList;
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
 * Slice 1: component stock linkage via silvershop/stock.
 * Slice 2: pricing — fixed (the inherited BasePrice) or the sum of the bundled products, with an optional
 * bundle discount, plus "you save" accessors comparing the selling price to the components bought separately.
 * Slice 4: items can be marked optional. Required items set the base price and are always fulfilled; optional
 * items are extras the customer selects at add-to-cart time (see {@link BundleOrderItem}).
 *
 * @property string $PricingType
 * @property string $DiscountType
 * @property float $DiscountValue
 */
class BundleProduct extends Product
{
    private static string $table_name = 'SilverShop_BundleProduct';

    /**
     * Bundles are added to the cart as a {@link BundleOrderItem}, which records the selected optional extras.
     */
    private static string $order_item = BundleOrderItem::class;

    private static string $singular_name = 'Bundle';

    private static string $plural_name = 'Bundles';

    private static string $description = 'Groups other products into one purchasable bundle.';

    private static string $icon_class = 'font-icon-box';

    /**
     * @var array<string, string>
     */
    private static array $db = [
        // Fixed = use the inherited BasePrice; Sum = total of the bundled products' selling prices.
        'PricingType' => "Enum('Fixed,Sum','Fixed')",
        // Applied only when PricingType is Sum.
        'DiscountType' => "Enum('None,Percentage,Amount','None')",
        'DiscountValue' => 'Decimal(10,2)',
    ];

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

    /**
     * @var array<string, string>
     */
    private static array $casting = [
        'ComponentsPrice' => 'Currency',
        'OptionalsTotal' => 'Currency',
        'Savings' => 'Currency',
        'SavingsPercentage' => 'Int',
    ];

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

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

        $fields->removeByName(['PricingType', 'DiscountType', 'DiscountValue']);
        $fields->addFieldsToTab('Root.Pricing', [
            DropdownField::create('PricingType', _t(self::class . '.PricingType', 'Bundle price'), [
                'Fixed' => _t(self::class . '.PricingFixed', 'Fixed price (use the Base Price above)'),
                'Sum' => _t(self::class . '.PricingSum', "Sum of the bundled products' prices"),
            ]),
            DropdownField::create('DiscountType', _t(self::class . '.DiscountType', 'Bundle discount'), [
                'None' => _t(self::class . '.DiscountNone', 'No discount'),
                'Percentage' => _t(self::class . '.DiscountPercentage', 'Percentage off'),
                'Amount' => _t(self::class . '.DiscountAmount', 'Fixed amount off'),
            ])->setDescription(_t(
                self::class . '.DiscountDesc',
                'Applied only when the price is the sum of the bundled products.'
            )),
            NumericField::create('DiscountValue', _t(self::class . '.DiscountValue', 'Discount value'))
                ->setScale(2)
                ->setDescription(_t(
                    self::class . '.DiscountValueDesc',
                    'A percentage (0–100) or a fixed amount, depending on the discount type above.'
                )),
        ]);

        $fields->dataFieldByName('BasePrice')?->setDescription(_t(
            self::class . '.BasePriceDesc',
            'Used when the bundle price is "Fixed". Ignored when the price is the sum of the bundled products.'
        ));

        return $fields;
    }

    /**
     * Bundle selling price:
     *  - Fixed: the inherited BasePrice (parent handles currency conversion and price extensions);
     *  - Sum: the total of the bundled products' selling prices, less any bundle discount.
     */
    public function sellingPrice(): float
    {
        if ($this->PricingType !== 'Sum') {
            return parent::sellingPrice();
        }

        $price = $this->applyBundleDiscount($this->getComponentTotal());
        $this->extend('updateSellingPrice', $price);
        $price = $price < 0 ? 0.0 : $price;

        return round($price, (int) Order::config()->get('rounding_precision'));
    }

    /**
     * The always-included items (set the base price).
     */
    public function RequiredItems(): HasManyList
    {
        return $this->BundleItems()->filter('Optional', 0);
    }

    /**
     * The opt-in extras.
     */
    public function OptionalItems(): HasManyList
    {
        return $this->BundleItems()->filter('Optional', 1);
    }

    /**
     * Raw total of the REQUIRED items' selling prices (component price x per-bundle quantity), in the active
     * currency. Not rounded or discounted — the basis for Sum pricing and the savings comparison. Optional
     * extras are priced per order line (see {@link BundleOrderItem}), not here.
     */
    public function getComponentTotal(): float
    {
        $total = 0.0;
        /** @var BundleItem $item */
        foreach ($this->RequiredItems() as $item) {
            $total += $item->getSubTotal();
        }

        return $total;
    }

    /**
     * Raw total of the OPTIONAL items' selling prices, for "add these extras" display. Cast to Currency.
     */
    public function getOptionalsTotal(): float
    {
        $total = 0.0;
        /** @var BundleItem $item */
        foreach ($this->OptionalItems() as $item) {
            $total += $item->getSubTotal();
        }

        return $total;
    }

    /**
     * What the contents would cost bought separately (rounded). Cast to Currency for templates.
     */
    public function getComponentsPrice(): float
    {
        return round($this->getComponentTotal(), (int) Order::config()->get('rounding_precision'));
    }

    /**
     * Amount saved versus buying the components separately (0 when there is no saving). Cast to Currency.
     */
    public function getSavings(): float
    {
        $saving = $this->getComponentTotal() - $this->sellingPrice();

        return $saving > 0 ? round($saving, (int) Order::config()->get('rounding_precision')) : 0.0;
    }

    public function getSavingsPercentage(): int
    {
        $total = $this->getComponentTotal();
        if ($total <= 0) {
            return 0;
        }

        return (int) round($this->getSavings() / $total * 100);
    }

    public function HasSavings(): bool
    {
        return $this->getSavings() > 0;
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

        if (!$this->BundleItems()->exists()) {
            return false;
        }

        // Only required items gate purchasability — an out-of-stock optional extra does not block the bundle.
        /** @var BundleItem $item */
        foreach ($this->RequiredItems() as $item) {
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

    private function applyBundleDiscount(float $price): float
    {
        return match ($this->DiscountType) {
            'Percentage' => $price * (1 - min(100.0, max(0.0, (float) $this->DiscountValue)) / 100),
            'Amount' => max(0.0, $price - (float) $this->DiscountValue),
            default => $price,
        };
    }
}
