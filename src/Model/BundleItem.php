<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Model;

use SilverShop\Extension\ProductVariationsExtension;
use SilverShop\Model\Buyable;
use SilverShop\Model\Variation\Variation;
use SilverShop\Page\Product;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * One line of a {@link BundleProduct}: a component product plus the quantity of it the bundle contains.
 *
 * Modelled as a join object (rather than a flat many_many) so each component carries its own attributes.
 * Slice 1 stores the quantity and sort order; later features add columns here — optional flag, price
 * contribution, choice group, variation — without reshaping the bundle.
 *
 * @property int $Quantity
 * @property int $SortOrder
 * @property bool $Optional
 * @property int $ProductID
 * @property int $BundleID
 * @property int $ChoiceGroupID
 * @property int $VariationID
 * @method BundleProduct Bundle()
 * @method Product Product()
 * @method BundleChoiceGroup ChoiceGroup()
 * @method Variation Variation()
 */
class BundleItem extends DataObject
{
    private static string $table_name = 'SilverShop_BundleItem';

    /**
     * @var array<string, string>
     */
    private static array $db = [
        'Quantity' => 'Int',
        'SortOrder' => 'Int',
        // Required items are always part of the bundle; optional items are extras the customer opts into.
        'Optional' => 'Boolean',
    ];

    /**
     * @var array<string, string>
     */
    private static array $has_one = [
        'Bundle' => BundleProduct::class,
        'Product' => Product::class,
        // When set, this item is one of the options of a mix-and-match choice group (not a plain
        // required/optional item); the customer picks it as part of that group's selection.
        'ChoiceGroup' => BundleChoiceGroup::class,
        // When set, the line targets this specific variation of the product rather than the whole product.
        'Variation' => Variation::class,
    ];

    /**
     * @var array<string, int>
     */
    private static array $defaults = [
        'Quantity' => 1,
    ];

    private static string $default_sort = 'SortOrder ASC, ID ASC';

    /**
     * @var array<string, string>
     */
    private static array $summary_fields = [
        'Product.Title' => 'Product',
        'Quantity' => 'Quantity',
        'Optional.Nice' => 'Optional',
    ];

    /**
     * @var array<string, string>
     */
    private static array $casting = [
        'SubTotal' => 'Currency',
    ];

    /**
     * The buyable this line resolves to: the chosen {@link Variation} when set, otherwise the whole
     * {@link Product}. Used for price, stock and purchasability.
     */
    public function Buyable(): ?Buyable
    {
        if ($this->VariationID) {
            $variation = $this->Variation();
            if ($variation && $variation->exists()) {
                return $variation;
            }
        }

        $product = $this->Product();

        return ($product && $product->exists()) ? $product : null;
    }

    /**
     * The contribution of this line to the bundle: the buyable's selling price times its bundle quantity.
     * Cast to Currency for templates.
     */
    public function getSubTotal(): float
    {
        $buyable = $this->Buyable();
        if (!$buyable) {
            return 0.0;
        }

        return $buyable->sellingPrice() * max(1, (int) $this->Quantity);
    }

    /**
     * Display title for this line — the variation's title when set, otherwise the product's.
     */
    public function getBuyableTitle(): string
    {
        if ($this->VariationID) {
            $variation = $this->Variation();
            if ($variation && $variation->exists()) {
                return (string) $variation->getTitle();
            }
        }

        $product = $this->Product();

        return ($product && $product->exists()) ? (string) $product->getTitle() : '';
    }

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['BundleID', 'SortOrder']);

        $products = Product::get()
            ->exclude('ClassName', BundleProduct::class)
            ->map('ID', 'Title')
            ->toArray();

        $fields->replaceField(
            'ProductID',
            DropdownField::create('ProductID', _t(self::class . '.Product', 'Product'), $products)
                ->setEmptyString(_t(self::class . '.SelectProduct', '(Select a product)'))
        );

        $fields->replaceField(
            'Quantity',
            NumericField::create('Quantity', _t(self::class . '.Quantity', 'Quantity'))
                ->setScale(0)
                ->setDescription(_t(
                    self::class . '.QuantityDesc',
                    'How many of this product are included in one bundle.'
                ))
        );

        $fields->dataFieldByName('Optional')?->setDescription(_t(
            self::class . '.OptionalDesc',
            'An optional extra the customer can add. Required items are always included and set the base price.'
        ));

        $bundle = $this->Bundle();
        $groups = ($bundle && $bundle->exists())
            ? $bundle->ChoiceGroups()->map('ID', 'Title')->toArray()
            : [];
        if ($groups !== []) {
            $fields->replaceField(
                'ChoiceGroupID',
                DropdownField::create('ChoiceGroupID', _t(self::class . '.ChoiceGroup', 'Choice group'), $groups)
                    ->setEmptyString(_t(self::class . '.NoChoiceGroup', '(none — a required or optional item)'))
                    ->setDescription(_t(
                        self::class . '.ChoiceGroupDesc',
                        'Make this product one of a mix-and-match group\'s options instead of required/optional.'
                    ))
            );
        } else {
            $fields->removeByName('ChoiceGroupID');
        }

        $product = $this->Product();
        $variations = ($product && $product->exists()
            && $product->hasExtension(ProductVariationsExtension::class)
            && $product->Variations()->exists())
            ? $product->Variations()->map('ID', 'Title')->toArray()
            : [];
        if ($variations !== []) {
            $fields->replaceField(
                'VariationID',
                DropdownField::create('VariationID', _t(self::class . '.Variation', 'Variation'), $variations)
                    ->setEmptyString(_t(self::class . '.WholeProduct', '(the whole product)'))
                    ->setDescription(_t(
                        self::class . '.VariationDesc',
                        'Target a specific variation of this product instead of the whole product.'
                    ))
            );
        } else {
            $fields->removeByName('VariationID');
        }

        return $fields;
    }

    /**
     * Whether this item is an option of a mix-and-match choice group (rather than required/optional).
     */
    public function IsChoice(): bool
    {
        return (bool) $this->ChoiceGroupID;
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if ($this->ProductID && $this->Product() instanceof BundleProduct) {
            $result->addError(_t(self::class . '.NoNestedBundles', 'A bundle cannot contain another bundle.'));
        }

        if ((int) $this->Quantity < 1) {
            $result->addFieldError('Quantity', _t(self::class . '.QuantityMin', 'Quantity must be at least 1.'));
        }

        if ($this->VariationID) {
            $variation = $this->Variation();
            if (!$variation || !$variation->exists() || (int) $variation->ProductID !== (int) $this->ProductID) {
                $result->addFieldError('VariationID', _t(
                    self::class . '.VariationMismatch',
                    'The chosen variation must belong to the selected product.'
                ));
            }
        }

        return $result;
    }

    public function canView($member = null): bool
    {
        $bundle = $this->Bundle();
        if ($bundle && $bundle->exists()) {
            return $bundle->canView($member);
        }

        return Product::singleton()->canView($member instanceof Member ? $member : null);
    }

    public function canEdit($member = null): bool
    {
        $bundle = $this->Bundle();
        if ($bundle && $bundle->exists()) {
            return $bundle->canEdit($member);
        }

        return Product::singleton()->canEdit($member instanceof Member ? $member : null);
    }

    public function canDelete($member = null): bool
    {
        return $this->canEdit($member);
    }

    public function canCreate($member = null, $context = []): bool
    {
        return Product::singleton()->canEdit($member instanceof Member ? $member : null);
    }
}
