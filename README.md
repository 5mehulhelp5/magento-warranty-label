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
| Legal Guarantee Notice Placements | Display mode per placement: header, footer, category, search, cart, checkout, success page, order confirmation email |
| EU GARAN Label | Kill switch, display mode per placement, and the guarantee terms attachment |

Every placement takes one of three modes:

- **Off** — no output.
- **Direct** — the official graphic is shown inline.
- **Nested** — a button opens a native `<dialog>` containing the full graphic, as permitted by the EU practical
  guidelines. Use this wherever the container is narrower than the configured minimum width; the checkout sidebar of
  most themes is.

**Label language is not the locale.** `general/language` selects which of the 24 official language versions is shown;
it falls back to the locale language, then to English. A German-locale store view serving English-speaking customers
can therefore show the English notice.

## Product data for GARAN

Four EAV attributes are added to **simple products only**, because the model identifier belongs to the variant:

| Attribute | Notes |
|---|---|
| `garan_brand` | Width-validated against the label field |
| `garan_model_identifier` | Width-validated against the label field |
| `garan_duration_years` | Accepts `4,5` and `4.5`; must be > 2, <= 99 and a multiple of 0.5 |
| `garan_terms_url` | Must be a valid http(s) URL; without it no label is rendered |

A label appears only when all four are valid. Configurable products resolve through the selected child — never the
parent, and never before a variant is chosen. Bundles resolve across all children.

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

Luma and MageSuite are supported out of the box through layout XML and Knockout components. Placements are ordinary
layout containers, so a project theme can move a block in its own theme source.

Hyvä is prepared but not part of this release.

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
