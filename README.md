# SilverShop Product Bundles

Sell a product that groups other products — a bundle — for [SilverShop](https://github.com/silvershop/silvershop-core) on Silverstripe CMS 6.

A **Bundle** is a real product (catalog page, price, cart and checkout behaviour) whose contents are a list of
**bundle items**, each a component product plus the quantity of it the bundle contains. When a bundle is
purchased, the stock of each component is decremented automatically; when the order is cancelled, it is
restored.

> **Status — slice 1 (foundation).** Fixed price + per-component quantities + component stock linkage. The
> data model is built around a join object so the features on the roadmap below slot in without reshaping the
> schema. See [Roadmap](#roadmap).

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
2. Set its price on the **Pricing** tab, exactly like a normal product. In this release a bundle is
   **fixed-price**: the price you set is what the customer pays.
3. Open the **Bundle** tab and add the products it contains, giving each a **quantity** (e.g. 2× Filter + 1×
   Housing). A bundle cannot contain another bundle.

### Stock linkage

With `silvershop/stock` installed, each component's warehouse stock is adjusted automatically:

- **On purchase** — placing an order for *N* bundles decrements each component by *N × its bundle quantity*.
- **On cancellation** (`AdminCancelled` / `MemberCancelled`) — the same amount is returned to stock.

A bundle is only **purchasable** when it has at least one component and **every** component can be purchased
for the required quantity. Leave the bundle itself without its own stock record (or unlimited) — availability
is derived from its components.

## Configuration

The module registers its extensions and admin model via `_config/product-bundles.yml`:

| Target | Extension | Purpose |
| --- | --- | --- |
| `SilverShop\Model\OrderItem` | `BundleProductOrderItemExtension` | Decrement component stock on placement |
| `SilverShop\Model\Order` | `BundleOrderExtension` | Restore component stock on cancellation |
| `SilverShop\Admin\ProductCatalogAdmin` | *(managed model)* | Lists bundles in the Products admin |

No YAML configuration is required for the default behaviour.

## Roadmap

Planned slices, each building on the `BundleItem` join object without a schema rebuild:

- **Pricing** — dynamic (sum of components) and `sum − discount` pricing, with a "you save" display.
- **Frontend** — a template rendering the bundle's contents and savings on the product page and in the cart.
- **Optional items / extras** — components the customer can opt into, with price adjustment.
- **Mix-and-match** — choice groups ("pick 3 of these 6") with min/max selection.
- **Variations** — allow a bundled line to target a specific product variation.

## Development

```bash
composer install
composer lint   # PHP_CodeSniffer (PSR-12)
composer stan   # PHPStan (level 5) + silverstan
composer test   # PHPUnit
```

## Licence

BSD-3-Clause.
