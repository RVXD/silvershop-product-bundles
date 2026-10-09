# SilverShop Product Bundles

Sell a product that groups other products — a bundle — for [SilverShop](https://github.com/silvershop/silvershop-core) on Silverstripe CMS 6.

A **Bundle** is a real product (catalog page, price, cart and checkout behaviour) whose contents are a list of
**bundle items**, each a component product plus the quantity of it the bundle contains. When a bundle is
purchased, the stock of each component is decremented automatically; when the order is cancelled, it is
restored.

> **Status.** Per-component quantities + component stock linkage + flexible pricing (fixed, or sum of the
> components with an optional bundle discount) with savings accessors + optional opt-in extras + mix-and-match
> choice groups (min/max, validated) + per-line **variations** + frontend includes (product page and cart).
> All selections are priced and fulfilled per line. See [Roadmap](#roadmap).

## Requirements

- PHP 8.3+
- `silverstripe/framework` ^6.0
- `silvershop/core` ^6
- `silvershop/stock` ^6 *(optional — enables the stock linkage; the module is inert on stock without it)*

## Installation

```bash
composer require silvershop/product-bundles
```

Then run a dev/build with a flush:

```
/dev/build?flush=all
```

## Usage

1. In the CMS, add a **Bundle** where you would add a Product (it appears in the catalog alongside Products,
   and in the **Products** admin).
2. Open the **Bundle** tab and add the products it contains, giving each a **quantity** (e.g. 2× Filter + 1×
   Housing) — and, optionally, a specific **variation** of that product. Tick **Optional** for opt-in extras,
   or add **choice groups** and assign items to them for "pick N of these" (see below). A bundle cannot
   contain another bundle.
3. Choose how it is priced on the **Pricing** tab (see below).

### Pricing

On the **Pricing** tab, **Bundle price** selects how the selling price is calculated:

- **Fixed price** — the customer pays the **Base Price** you set, exactly like a normal product.
- **Sum of the bundled products' prices** — the price is the total of each component's selling price ×
  its bundle quantity, recalculated automatically as component prices change.

When the price is the *sum*, you can apply a **Bundle discount** — a **percentage** or a **fixed amount**
off that total.

Either way the bundle exposes savings-versus-separate accessors for templates and reporting:

| Accessor | Meaning |
| --- | --- |
| `$ComponentsPrice` | What the contents would cost bought separately (cast to Currency) |
| `$Savings` | Amount saved versus buying separately, `0` when there is none (Currency) |
| `$SavingsPercentage` | The saving as a whole-number percent |
| `$HasSavings` | Whether there is any saving to show |

The price flows through the cart and checkout like any product (`sellingPrice()` is overridden), so order
totals, tax and modifiers all see the bundle price.

Only the **required** items set this base price. Optional extras are priced per order line (see below).

### Optional extras

Tick **Optional** on a bundle item to make it an opt-in extra rather than a fixed part of the bundle:

- The **base price** (fixed or sum) covers only the required items; the add-to-cart form gains a checkbox
  per extra, labelled with its price (`+ €…`).
- Each selected extra is added to that line's unit price, and its stock is decremented on purchase /
  restored on cancellation — exactly like the required items.
- Different extra selections are **separate cart lines**; an out-of-stock extra never blocks the bundle.

Selections are stored on a `BundleOrderItem` (the bundle's `order_item`), which also exposes
`$FulfilmentItems` (required + chosen items) for cart templates.

### Mix-and-match choice groups

For "pick N of these" bundles, add **choice groups** on the Bundle tab (each with a **min** and **max**),
then assign bundle items to a group via each item's **Choice group** dropdown:

- The add-to-cart form shows one checkbox set per group, labelled with its range ("choose 3", "choose 1–2"),
  and the selection is **validated** against each group's min/max before the bundle is added.
- Chosen options are priced and stock-tracked per line, just like optional extras (they share the same
  `SelectedItemIDs` on the `BundleOrderItem`).
- `BundleProduct::validateSelection(array $ids)` exposes the min/max check for custom flows.

A bundle item is therefore one of: **required** (always included), **optional** (opt-in extra), or a
**choice** (an option of a group) — set by its Optional flag and Choice group.

### Variations

A bundle item can target a **specific variation** of its product (e.g. "T-Shirt — size L") rather than the
whole product. On the item, pick a **Variation** (the dropdown lists the product's variations once it has
any); the variation must belong to the selected product.

The variation then drives that line's **price, stock and purchasability** (via its own
`sellingPrice()`/stock), and its title is shown on the product page and in the cart. This works for required,
optional and choice items alike.

### Stock linkage

With `silvershop/stock` installed, each component's warehouse stock is adjusted automatically:

- **On purchase** — placing an order for *N* bundles decrements each component by *N × its bundle quantity*.
- **On cancellation** (`AdminCancelled` / `MemberCancelled`) — the same amount is returned to stock.

A bundle is only **purchasable** when it has at least one component and **every** component can be purchased
for the required quantity. Leave the bundle itself without its own stock record (or unlimited) — availability
is derived from its components.

## Showing bundles on the frontend

The module ships self-styled template includes. Add them to your theme where you want them.

**Product page** — in your product template (e.g. `themes/<theme>/templates/SilverShop/Page/Layout/Product.ss`),
output the contents + savings block:

```ss
<% include SilverShop\ProductBundles\BundleContents %>
```

**Cart / order line** — show a compact contents list under a bundle line, with the bundle in scope:

```ss
<% with $Product %><% include SilverShop\ProductBundles\BundleCartSummary %><% end_with %>
```

Both includes output nothing for non-bundle products, so they are safe to drop into shared templates. They are
self-styled; override either by copying it into your theme at the same path.

## Configuration

The module registers its extensions and admin model via `_config/product-bundles.yml`:

| Target | Extension | Purpose |
| --- | --- | --- |
| `SilverShop\Model\OrderItem` | `BundleProductOrderItemExtension` | Decrement component stock on placement |
| `SilverShop\Model\Order` | `BundleOrderExtension` | Restore component stock on cancellation |
| `SilverShop\Admin\ProductCatalogAdmin` | *(managed model)* | Lists bundles in the Products admin |

No YAML configuration is required for the default behaviour.

## Roadmap

Done: per-component quantities + stock linkage; flexible pricing (fixed / sum / sum − discount) with savings
accessors; frontend includes for product page and cart; optional opt-in extras; mix-and-match choice groups
(min/max, validated); per-line variations — all priced and fulfilled per line.

Possible future additions (not yet built): tiered/quantity price breaks, a "frequently bought together"
suggester, and a bundle-as-its-own-SKU stock mode.

## Translations

All front-end and CMS strings go through `_t()` / `<%t %>`. Ships with **en, nl, de, fr, es, it** (`lang/*.yml`);
override or add locales the usual Silverstripe way.

## Development

```bash
composer install
composer lint   # PHP_CodeSniffer (PSR-12)
composer stan   # PHPStan (level 5) + silverstan
composer test   # PHPUnit
```

## Licence

BSD-3-Clause.
