# CopeX_WarrantyLabel

[![Lint](https://github.com/CopeX/magento-warranty-label/actions/workflows/lint.yml/badge.svg)](https://github.com/CopeX/magento-warranty-label/actions/workflows/lint.yml)
[![Unit tests](https://github.com/CopeX/magento-warranty-label/actions/workflows/tests.yml/badge.svg)](https://github.com/CopeX/magento-warranty-label/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Magento 2 extension implementing the two EU pre-contractual information tools that become mandatory on
**27 September 2026** under Directive **(EU) 2024/825** and Implementing Regulation **(EU) 2025/1960**:

1. **Harmonised notice on the legal guarantee of conformity** (Annex I) — required of every shop selling goods to
   consumers in the EU.
2. **EU GARAN label** (Annex II) — required wherever a producer offers a free commercial guarantee of durability
   covering the entire good for more than two years.

> **Compliance reference** — the mapping of each legal requirement to its implementation, including the known gaps,
> is maintained in [`docs/COMPLIANCE-DE.md`](docs/COMPLIANCE-DE.md) (German). Hand that document to legal counsel.
>
> **Asset provenance and licences** — see [`view/base/web/ASSETS.md`](view/base/web/ASSETS.md).
>
> This module does not constitute legal advice.

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Product data for GARAN](#product-data-for-garan)
5. [Guarantee terms attachment](#guarantee-terms-attachment)
6. [Audit command](#audit-command)
7. [Themes](#themes)
8. [Translations](#translations)
9. [Testing](#testing)

## Requirements

- PHP >= 8.2 with **ext-gd compiled with FreeType** (the GARAN label is rendered server-side onto the official
  background images; without FreeType the renderer throws and no label is produced)
- Magento 2.4.x

## Installation

```bash
composer require copex/module-warranty-label
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

To remove it again, use `bin/magento module:uninstall CopeX_WarrantyLabel --remove-data`. The flag is not
optional: without it the four GARAN attributes survive with a `backend_model` pointing at classes that are
gone, and every product page then fails. The uploaded guarantee terms PDF is deliberately kept.

The module is **off by default**. `copex_warrantylabel/general/enabled = 0` is also the kill switch: setting it to
"No" removes every notice and label output for that scope.

## Configuration

**Stores → Configuration → Sales → EU Guarantee Notice & GARAN Label**, all settings scoped per store view.

| Group | Purpose |
|---|---|
| General Settings | Kill switch, label language, nested button text, notice alt text, minimum notice width, product types without a legal guarantee |
| Legal Guarantee Notice Placements | Display mode per placement: header, footer, category, search, cart, checkout, success page; yes/no for the order confirmation email |
| EU GARAN Label | Kill switch, display mode per placement, yes/no for the email, and the guarantee terms attachment |

Every storefront placement takes one of four modes:

- **Off** — no output.
- **Direct** — the official graphic is shown inline.
- **Nested** — a button opens a native `<dialog>` containing the full graphic, as permitted by the EU practical
  guidelines. Use this wherever the container is narrower than the configured minimum width; the checkout sidebar of
  most themes is.
- **Dialog only** — the same dialog without the button, for shops that place their own trigger. See below.

The two email fields are yes/no, because an email cannot open a dialog.

Everything ships switched off — both kill switches and every single placement. A freshly installed module changes
nothing in the storefront until the placements are chosen deliberately; `docs/EN.md` section 4.2 suggests where to
start.

### Your own trigger

In **Dialog only** the module renders the `<dialog>` and leaves the trigger to you. Any element carrying the class
`copex-wl-trigger` and an `aria-controls` with the dialog id opens it, wherever it sits on the page — a CMS block, the
footer, a template of your theme:

```html
<button type="button" class="copex-wl-trigger" aria-controls="copex-wl-notice-footer">
    Legal guarantee
</button>
```

The ids are `copex-wl-notice-<placement>` for the notice — `copex-wl-notice-header`, `-footer`, `-cart`, `-category`,
`-search`, `-checkout`, `-success` — and `copex-wl-garan-pdp` for the GARAN label on the product page. On the success
page and in the checkout the GARAN ids carry the item id, so read them from the rendered markup.

Triggers are bound when the page loads. One added later, by a script of your own, needs to be in the DOM before that.

**Label language is not the locale.** `general/language` selects which of the 24 official language versions is shown;
it falls back to the locale language, then to English. A German-locale store view serving English-speaking customers
can therefore show the English notice.

## Product data for GARAN

Four EAV attributes are added to **every product type**, with **store view** scope:

| Attribute | Notes |
|---|---|
| `garan_brand` | Width-validated against the label field |
| `garan_model_identifier` | Width-validated against the label field |
| `garan_duration_years` | Accepts `4,5` and `4.5`; must be > 2, <= 99 and a multiple of 0.5 |
| `garan_terms_url` | Must be a valid http(s) URL; without it no label is rendered |

A label appears only when all four are valid. Configurable products resolve through the selected child, never before
a variant is chosen; a child whose field is empty inherits it from its configurable parent. Bundles resolve across all
children.

**Fallbacks for empty fields.** The product always wins; these only fill a gap:

| Setting | Effect |
|---|---|
| `garan/brand_source` | `garan_attribute` (default), `product_attribute` (any text/textarea/select attribute, chosen in `garan/brand_attribute`, resolved to its option label), or `config_value` (the fixed `garan/brand_value`) |
| `garan/model_source` | `garan_attribute` (default) or `product_name` |
| `garan/terms_url` | One URL for every product without its own |

`model_source = product_name` is width-validated like any other value: a name too long for the shared brand/model line
is rejected, not shrunk, and the product gets **no label** with the audit reason `too_long`. Spot-check with
`bin/magento copex:warranty-label:audit --store=<id>`.

**Half-year durations:** at the official font size only whole years 3–99 and `7,5` fit in front of the calendar icon.
Other `,5` values are accepted as data but produce **no label** and the audit reason `duration_does_not_fit`, because
shrinking the type or moving the icon would breach the design rules.

## Guarantee terms attachment

The producer's guarantee statement must reach the consumer on a **durable medium** at the latest at delivery
(Art. 17(2) Directive (EU) 2019/771; § 9a (3) KSchG; § 479 (2) BGB). A link on a website does not satisfy this
(CJEU C-49/11, *Content Services*).

The module therefore attaches one configured PDF per store view to the order confirmation of every order containing a
GARAN product, without any third-party email extension. Configure it under the GARAN group:

| Setting | Meaning |
|---|---|
| `garan/attach_terms` | Enable the attachment |
| `garan/terms_file` | The PDF, uploaded in the admin |
| `garan/terms_filename` | File name the customer sees |

**The PDF must be uploaded through the admin.** `bin/magento config:set` cannot write `type="file"` fields, because
the backend model expects a real upload. The stored value is scope-prefixed: a default-scope upload yields
`default/file.pdf` below `pub/media/copex_warranty_label/terms/`, a store-view upload `stores/<id>/file.pdf`.

One document per store view is sufficient as long as it names the goods it covers.

## Audit command

```bash
bin/magento copex:warranty-label:audit
```

Lists products whose GARAN data is incomplete or invalid, with a reason code per row. The module never invents
guarantee data — deciding which products qualify and maintaining their values is the merchant's responsibility.

## Themes

**Luma** (and Blank) is supported out of the box and is what the module is tested against. Every placement is an
ordinary Magento core container — `header-wrapper`, `footer`, `content`, `cart.summary`, `product.info.main`. No
third-party theme is required anywhere.

### Hyvä

Supported.
Keep the shipped defaults — `nested` for the storefront placements: the notice graphic is 420 x 594 px and `direct` pushes a Hyvä header or footer apart.

The GARAN label on the product page is the one placement a Hyvä project has to make itself.
The module's anchor resolves in Luma only, so the label otherwise renders at the bottom of the product column.
Place it in the theme:

```xml
<!-- app/design/frontend/<Vendor>/<theme>/Magento_Catalog/layout/catalog_product_view.xml -->
<referenceBlock name="copex.warrantylabel.garan.product" remove="true"/>
<referenceBlock name="product.info">
    <block class="Magento\Catalog\Block\Product\View"
           name="copex.warrantylabel.garan.product.hyva"
           template="CopeX_WarrantyLabel::garan-product.phtml"
           ifconfig="copex_warrantylabel/garan/enabled">
        <arguments>
            <argument name="view_model" xsi:type="object">CopeX\WarrantyLabel\ViewModel\GaranProduct</argument>
        </arguments>
    </block>
</referenceBlock>
```

```phtml
<!-- .../Magento_Catalog/templates/product/view/product-info.phtml, where the label belongs -->
<?= $block->getChildHtml('copex.warrantylabel.garan.product.hyva') ?>
```

### Checkout

The checkout uses two regions of `Magento_Checkout`:

| Output | jsLayout node | Where Luma shows it |
|---|---|---|
| Notice and the GARAN labels of all items | `steps > billing-step > payment > payments-list > before-place-order` | inside the selected payment method, directly above the place order button — next to the checkout agreements |
| GARAN labels of one item | `sidebar > summary > cart_items > details`, region `after_details` | below the item in the order summary |

Magento renders `before-place-order` once per payment method, so the dialog ids of the nested display are made
unique at runtime. **A payment method whose template leaves that region out shows neither the checkout agreements
nor the notice** — select every payment method of the shop once and look for the notice above its button.

A project theme that moves the price out of `product.info.main` also has to move the GARAN block, and a checkout
whose place order button lives outside the payment methods — or a payment method without the region — needs the
two checkout components somewhere else. Do that in the project, not in this module. The parent has to be a node
with a component of its own, otherwise the children are never attached; `afterMethods` below the payment methods
is one Magento provides:

```xml
<!-- app/design/frontend/<Vendor>/<theme>/Magento_Checkout/layout/checkout_index_index.xml -->
<referenceBlock name="checkout.root">
    <arguments>
        <argument name="jsLayout" xsi:type="array">
            <item name="components" xsi:type="array">
                <item name="checkout" xsi:type="array">
                    <item name="children" xsi:type="array">
                        <item name="steps" xsi:type="array">
                            <item name="children" xsi:type="array">
                                <item name="billing-step" xsi:type="array">
                                    <item name="children" xsi:type="array">
                                        <item name="payment" xsi:type="array">
                                            <item name="children" xsi:type="array">
                                                <!-- the new place -->
                                                <item name="afterMethods" xsi:type="array">
                                                    <item name="children" xsi:type="array">
                                                        <item name="copex-warranty-notice" xsi:type="array">
                                                            <item name="component" xsi:type="string">CopeX_WarrantyLabel/js/view/checkout/notice</item>
                                                        </item>
                                                        <item name="copex-warranty-garan-summary" xsi:type="array">
                                                            <item name="component" xsi:type="string">CopeX_WarrantyLabel/js/view/checkout/garan-summary</item>
                                                        </item>
                                                    </item>
                                                </item>
                                                <!-- the originals, switched off so nothing shows twice -->
                                                <item name="payments-list" xsi:type="array">
                                                    <item name="children" xsi:type="array">
                                                        <item name="before-place-order" xsi:type="array">
                                                            <item name="children" xsi:type="array">
                                                                <item name="copex-warranty-notice" xsi:type="array">
                                                                    <item name="config" xsi:type="array">
                                                                        <item name="componentDisabled" xsi:type="boolean">true</item>
                                                                    </item>
                                                                </item>
                                                                <item name="copex-warranty-garan-summary" xsi:type="array">
                                                                    <item name="config" xsi:type="array">
                                                                        <item name="componentDisabled" xsi:type="boolean">true</item>
                                                                    </item>
                                                                </item>
                                                            </item>
                                                        </item>
                                                    </item>
                                                </item>
                                            </item>
                                        </item>
                                    </item>
                                </item>
                            </item>
                        </item>
                    </item>
                </item>
            </item>
        </argument>
    </arguments>
</referenceBlock>
```

**Hyvä** is partially usable today and not yet complete:

| | Status |
|---|---|
| Notice in *direct* mode — header, footer, category, search, cart, success page | works — `notice.phtml` renders server-side and loads no JavaScript |
| Nested display (dialog) and the variant switch on the product page | not yet — both load through RequireJS, and the variant switch also uses jQuery |
| Checkout | not yet — the three components extend `uiComponent` and render through Knockout templates |

Contributions for the Hyvä side are welcome.

## Translations

`i18n/de_DE.csv` and `i18n/de_AT.csv` ship with the module. Magento loads module CSVs by exact locale only, so a new
locale needs its own file rather than a language fallback.

## Testing

```bash
vendor/bin/phpunit --no-extensions -c dev/tests/unit/phpunit.xml.dist vendor/copex/module-warranty-label/Test/Unit
```

`GaranPngRendererTest` exercises real GD and FreeType in a temporary directory, and `FieldFitCheckerTest` reads the
bundled Inter TTFs, so both need a GD build with FreeType support.

Manual checks that no unit test replaces: scanning the QR code from screen and from a printed email, operating the
nested dialog by keyboard alone, and a screenshot pass per placement and store view.

## License

MIT — see [`LICENSE`](LICENSE). © CopeX GmbH.

The bundled EU artwork and the Inter fonts carry their own terms; `LICENSE` names them.
