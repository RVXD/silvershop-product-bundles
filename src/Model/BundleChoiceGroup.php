<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Model;

use SilverShop\Page\Product;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Member;

/**
 * A mix-and-match group within a {@link BundleProduct}: the customer chooses between MinChoices and
 * MaxChoices of the {@link BundleItem}s assigned to this group (e.g. "pick 3 of these 6").
 *
 * @property string $Title
 * @property int $MinChoices
 * @property int $MaxChoices
 * @property int $SortOrder
 * @property int $BundleID
 * @method BundleProduct Bundle()
 * @method HasManyList<BundleItem> Items()
 */
class BundleChoiceGroup extends DataObject
{
    private static string $table_name = 'SilverShop_BundleChoiceGroup';

    /**
     * @var array<string, string>
     */
    private static array $db = [
        'Title' => 'Varchar(255)',
        'MinChoices' => 'Int',
        'MaxChoices' => 'Int', // 0 = no upper limit
        'SortOrder' => 'Int',
    ];

    /**
     * @var array<string, string>
     */
    private static array $has_one = [
        'Bundle' => BundleProduct::class,
    ];

    /**
     * @var array<string, string>
     */
    private static array $has_many = [
        'Items' => BundleItem::class . '.ChoiceGroup',
    ];

    /**
     * @var array<string, int>
     */
    private static array $defaults = [
        'MinChoices' => 1,
        'MaxChoices' => 1,
    ];

    private static string $default_sort = 'SortOrder ASC, ID ASC';

    /**
     * @var array<string, string>
     */
    private static array $summary_fields = [
        'Title' => 'Title',
        'ChoiceRange' => 'Choose',
        'Items.Count' => 'Options',
    ];

    /**
     * @var array<string, string>
     */
    private static array $casting = [
        'ChoiceRange' => 'Varchar',
    ];

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['BundleID', 'SortOrder', 'Items']);
        $fields->dataFieldByName('MinChoices')?->setDescription(_t(
            self::class . '.MinDesc',
            'Fewest items the customer must choose from this group.'
        ));
        $fields->dataFieldByName('MaxChoices')?->setDescription(_t(
            self::class . '.MaxDesc',
            'Most items the customer may choose (0 = no limit).'
        ));

        if ($this->isInDB()) {
            $fields->addFieldToTab('Root.Main', LiteralField::create(
                'ItemsNote',
                '<p class="message notice">' . _t(
                    self::class . '.AssignNote',
                    'Assign products to this group from the bundle\'s Bundle tab (each bundle item picks its group).'
                ) . '</p>'
            ));
        }

        return $fields;
    }

    /**
     * Human-readable "Choose N" / "N–M" / "at least N" summary.
     */
    public function getChoiceRange(): string
    {
        $min = (int) $this->MinChoices;
        $max = (int) $this->MaxChoices;

        if ($max <= 0) {
            return $min > 0 ? _t(self::class . '.AtLeast', 'at least {min}', ['min' => $min]) : (string) 0;
        }
        if ($min === $max) {
            return (string) $min;
        }

        return "{$min}–{$max}";
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if ((int) $this->MinChoices < 0) {
            $result->addFieldError('MinChoices', _t(self::class . '.MinNegative', 'Minimum cannot be negative.'));
        }
        if ((int) $this->MaxChoices > 0 && (int) $this->MaxChoices < (int) $this->MinChoices) {
            $result->addFieldError('MaxChoices', _t(
                self::class . '.MaxLessThanMin',
                'Maximum must be at least the minimum (or 0 for no limit).'
            ));
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
