<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Model;

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
 * @method BundleProduct Bundle()
 * @method Product Product()
 * @method BundleChoiceGroup ChoiceGroup()
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
     * The contribution of this line to the bundle: the component's selling price times its bundle quantity.
     * Cast to Currency for templates.
     */
    public function getSubTotal(): float
    {
        $product = $this->Product();
        if (!$product || !$product->exists()) {
            return 0.0;
        }

        return $product->sellingPrice() * max(1, (int) $this->Quantity);
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
