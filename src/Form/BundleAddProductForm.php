<?php

declare(strict_types=1);

namespace SilverShop\ProductBundles\Form;

use SilverShop\Forms\AddProductForm;
use SilverShop\ProductBundles\Model\BundleItem;
use SilverShop\ProductBundles\Page\BundleProduct;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Model\List\SS_List;

/**
 * Add-to-cart form for a {@link BundleProduct}: adds a checkbox set for the optional extras and one per
 * mix-and-match choice group, combines the chosen item IDs into the single `SelectedItemIDs` stored on the
 * {@link \SilverShop\ProductBundles\Model\BundleOrderItem}, and validates each choice group's min/max before
 * adding to the cart. Selected into the form by {@see \SilverShop\ProductBundles\Extension\BundleFormExtension}.
 */
class BundleAddProductForm extends AddProductForm
{
    protected function getFormFields(RequestHandler $controller): FieldList
    {
        $fields = parent::getFormFields($controller);

        $bundle = $this->bundleFromController($controller);
        if (!$bundle instanceof BundleProduct) {
            return $fields;
        }

        $extras = $this->itemsSource($bundle->OptionalItems());
        if ($extras !== []) {
            $fields->push(
                CheckboxSetField::create('BundleExtras', _t(self::class . '.Extras', 'Optional extras'), $extras)
            );
        }

        foreach ($bundle->ChoiceGroups() as $group) {
            $options = $this->itemsSource($group->Items());
            if ($options === []) {
                continue;
            }

            $label = $group->Title . ' (' . _t(
                self::class . '.Choose',
                'choose {range}',
                ['range' => $group->getChoiceRange()]
            ) . ')';
            $fields->push(CheckboxSetField::create('BundleGroup_' . $group->ID, $label, $options));
        }

        return $fields;
    }

    public function addtocart(array $data, $form)
    {
        $buyable = $this->getBuyable($data);

        if ($buyable instanceof BundleProduct) {
            $ids = $this->collectSelectedIDs($data, $buyable);

            $validation = $buyable->validateSelection($ids);
            if (!$validation->isValid()) {
                foreach ($validation->getMessages() as $message) {
                    $form->sessionMessage($message['message'], 'bad');
                }

                return $this->getController()->redirectBack();
            }

            // Combine every selection into the single stored field so cart matching stays correct.
            $this->setSaveableFields(['SelectedItemIDs']);
            if ($ids !== []) {
                $data['SelectedItemIDs'] = implode(',', $ids);
            } else {
                unset($data['SelectedItemIDs']);
            }
        }

        return parent::addtocart($data, $form);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int>
     */
    private function collectSelectedIDs(array $data, BundleProduct $bundle): array
    {
        $names = ['BundleExtras'];
        foreach ($bundle->ChoiceGroups() as $group) {
            $names[] = 'BundleGroup_' . $group->ID;
        }

        $ids = [];
        foreach ($names as $name) {
            if (!empty($data[$name])) {
                $ids = array_merge($ids, (array) $data[$name]);
            }
        }

        $ids = array_map('intval', $ids);
        $allowed = array_map('intval', $bundle->SelectableItems()->column('ID'));
        $ids = array_values(array_unique(array_intersect($ids, $allowed)));
        sort($ids);

        return $ids;
    }

    /**
     * @return array<int, string>
     */
    private function itemsSource(SS_List $items): array
    {
        $source = [];
        /** @var BundleItem $item */
        foreach ($items as $item) {
            if (!$item->Buyable()) {
                continue;
            }

            $label = $item->getBuyableTitle();
            if ((int) $item->Quantity > 1) {
                $label = $item->Quantity . '× ' . $label;
            }

            $source[$item->ID] = $label . ' (+' . $item->obj('SubTotal')?->Nice() . ')';
        }

        return $source;
    }

    private function bundleFromController(RequestHandler $controller): ?BundleProduct
    {
        $record = method_exists($controller, 'data') ? $controller->data() : null;

        return $record instanceof BundleProduct ? $record : null;
    }
}
