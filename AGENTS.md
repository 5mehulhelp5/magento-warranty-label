# CopeX_WarrantyLabel

## OVERVIEW

Implements the two EU pre-contractual information tools required from 27 September 2026 by Directive (EU) 2024/825
and Implementing Regulation (EU) 2025/1960:

1. **Harmonised notice on the legal guarantee of conformity** ("Gewährleistungshinweis") — mandatory for every shop
   selling goods to consumers. Official EU graphic per store-view language, shown per placement either directly or
   nested (button → dialog), in checkout, on the success page and in the order confirmation email.
2. **EU GARAN label** — only for simple products whose producer offers a free commercial guarantee of durability for the
   entire good and for more than two years. Brand, model identifier, duration and terms URL are EAV attributes on the
   simple product (variant). Rendered as cached PNGs (web + email): Inter text drawn onto blank backgrounds rasterised
   from the official EU label files.

Everything is off by default (`copex_warrantylabel/general/enabled = 0`); that flag is also the kill switch.
Source requirements: EU Commission "Practical guidelines for sellers and producers" (Ares(2026)4331985), cited as "LL p.X".

## STRUCTURE

```
Api/                                   GaranLabelResolverInterface, Data/GaranLabelDataInterface
Console/Command/AuditCommand.php       copex:warranty-label:audit — products with incomplete/invalid GARAN data
Model/Config.php                       all store-scope settings (modes per placement, language, min width, exclusions)
Model/Language/LanguageRegistry.php    24 EU languages → notice asset id + Your Europe URL (LL p.15-16)
Model/Source/                          DisplayMode (off/direct/nested), Language, ProductType
Model/Garan/Attributes.php             attribute codes + order item snapshot column name
Model/Garan/DurationParser.php         "4,5"/"4.5" → 4.5; > 2, ≤ 99, multiple of 0.5
Model/Garan/LabelValidator.php         single source of truth for GARAN data rules + audit reason codes
Model/Garan/FieldFitChecker.php        metric (Inter TTF) width checks + anchor constants of the label fields
Model/Garan/Resolver.php               product / quote item / order item (snapshot) → list of GaranLabelData
Model/Garan/GaranLabelData.php         immutable label value object (formatted duration uses a comma)
Model/Attribute/Backend/               Duration, LabelText (brand/model width), TermsUrl
Model/Render/NoticeRenderer.php        notice asset URLs, texts, links
Model/Render/GaranPngRenderer.php      GD + Inter TTF onto blank@4x background, cached in pub/media/copex_warranty_label/garan
                                       (web and email; VARIANT_FULL / VARIANT_NESTED)
Model/Checkout/ConfigProvider.php      window.checkoutConfig.copexWarrantyLabel (notice + GARAN per quote item)
Plugin/Quote/ToOrderItemPlugin.php     writes the GARAN snapshot JSON to sales_order_item.copex_garan_label
Plugin/Sales/EmailItemsPlugin.php      afterToHtml on Magento\Sales\Block\Order\Email\Items: notice + GARAN list in the order email
Plugin/Mail/AttachPendingFiles.php     afterGetTransport (sortOrder 100, last): attaches registered files in place
Model/Email/TermsDocument.php          resolves + validates the configured guarantee terms PDF below pub/media
Model/Email/PendingAttachments.php     request-scoped registry between observer and mail plugin
Model/Email/EmailAttachment.php        one attached file: name, content, mime type
Model/Email/GraphicAttachments.php     notice PNG + one GARAN label PNG per item, as attachments
Observer/RegisterEmailAttachments.php   email_order_set_template_vars_before: registers terms PDF and graphics
Setup/Patch/Data/AddGaranProductAttributes.php
ViewModel/                             Notice, GaranList (+ PDP view model)
view/base/web/notice/                  official notice SVG/PNG per language + CHECKSUMS (see ASSETS.md)
view/base/web/garan/                   official label files (reference) + blank@4x PNG backgrounds for the renderer
view/base/web/fonts/ttf/               Inter 4.1 (OFL) TTFs, used server-side by FieldFitChecker and GaranPngRenderer
view/frontend/                         layouts per placement, templates, dialog.js, checkout KO components, CSS
i18n/                                  de_DE.csv, de_AT.csv (Magento loads module CSVs by exact locale only)
```

## ARCHITECTURE

- **Display modes per placement** (`Config::getNoticeMode()` / `getGaranMode()`), default conservative: direct in checkout,
  success and email; nested in header, footer, category, search, cart and on the PDP.
- **Label language ≠ locale**: `general/language` per store view; falls back to the locale language, then English.
- **Email**: `{{layout handle="sales_email_order_items"}}` renders only the first root block, so extra layout blocks are
  silently dropped. Output is appended by `EmailItemsPlugin::afterToHtml` and wrapped in try/catch — the confirmation
  email must never fail. Emails run inside store emulation already; never start `App\Emulation` here.
- **GARAN data flow**: EAV attributes on the simple product → `LabelValidator` (render time, because bulk editors and mass update
  bypass backend models) → `Resolver` → `GaranPngRenderer` (cached PNG). At order placement `ToOrderItemPlugin`
  stores a JSON list per order item (configurable: selected child, bundle: every child); emails and the success page read
  that snapshot via `Resolver::forOrderItem()` for **visible items only** (children carry their own snapshot too).
- **Half-year durations**: at the official font size only whole years 3–99 and "7,5" fit in front of the calendar icon.
  Other ",5" values are valid data but yield no label and the audit reason `duration_does_not_fit` (decision 2026-09-15).
- **Guarantee terms attachment**: the producer's guarantee statement must reach the consumer on a durable medium at
  the latest at delivery (Art. 17(2) Directive (EU) 2019/771, § 9a (3) KSchG, § 479 (2) BGB); a link is not enough
  (CJEU C-49/11). One PDF per store view, uploaded in the admin (`config:set` cannot write `type="file"` fields).
  Observer registers it only for orders that carry GARAN labels; the mail plugin wraps the existing body in a
  `MixedPart` — `EmailMessage::getSymfonyMessage()` returns a `Message`, not an `Email`, so `attach()` does not exist.
  Never rebuild the message: other extensions (dropshipping, PDF customisers) plug into this chain too and
  replace the body when they carry attachments of their own.
- **Failure policy**: Resolver/FieldFitChecker throw on deployment errors (missing font) so admin and audit notice it;
  storefront view models, the checkout config provider and the email plugin catch `\Throwable`, log and show no label.

## WHERE TO LOOK

| Task | Location |
|---|---|
| Add/rename a setting | `etc/adminhtml/system.xml`, `etc/config.xml`, `Model/Config.php` |
| Change placement container | `view/frontend/layout/*.xml` (project themes move blocks in their theme source) |
| Your Europe URL of a language | `Model/Language/LanguageRegistry.php` |
| GARAN validation rules / reason codes | `Model/Garan/LabelValidator.php`, `Model/Garan/DurationParser.php` |
| Label field positions / widths | `Model/Garan/FieldFitChecker.php` constants (anchors measured against LL p.23/p.29) |
| PNG output size / cache key | `Model/Render/GaranPngRenderer.php` (bump `TEMPLATE_VERSION` after template changes) |
| Order email output | `Plugin/Sales/EmailItemsPlugin.php` |
| Checkout data | `Model/Checkout/ConfigProvider.php`, `view/frontend/web/js/view/checkout/` |
| Checkout position | `view/frontend/layout/checkout_index_index.xml` — notice + GARAN summary in `payments-list > before-place-order`, GARAN per item in `cart_items > details` (`after_details`) |
| Official assets, licences, QR measurement | `view/base/web/ASSETS.md` |
| Which legal requirement a change touches | `docs/COMPLIANCE-DE.md` (requirement → implementation → gaps) |

## ANTI-PATTERNS

- Never recolour, crop, filter, restyle or add elements to the official notice/label files (LL §2.1.4, §3.1.5).
- Never scale the notice below `min_width_px` (420 px keeps the smallest QR, FR 18.04 % of the width, at ≥ 2 cm).
  The 2 × 2 cm rule is explicit only for the GARAN label (LL §3.1.2); for the notice LL §2.1.2 requires a scannable QR.
- Prefer `nested` for a placement whose container is narrower than `min_width_px`: `.copex-wl-scroll` lets the
  notice scroll horizontally instead of overflowing (Luma checkout on a phone: 345 px), which is complete but looks
  cut off. The dialog shows it at full size.
- Never change font size or move the calendar icon to make a duration fit — no label instead.
- Never show a GARAN label for configurable parents or before a variant is selected (LL p.39, no ambiguity).
- Never add a layout block to `sales_email_order_items` for output — it is dropped; use the plugin.
- Never save the order or observe order save for the snapshot: dropshipping extensions split orders into purchase
  orders on save, and an extra save corrupts that state.
- Never hang a checkout component on a jsLayout node `Magento_Checkout` does not define (it was
  `sidebar > summary > before-place-order` once): without a parent component the child is created but never
  attached, renders nowhere and reports nothing — the notice was simply absent in Luma.
  `Test/Unit/Layout/CheckoutLayoutTest` compares every parent with the layout of `Magento_Checkout`.
- Never look a dialog up by id alone: `before-place-order` is rendered once per payment method, so ids repeat and
  `getElementById` returns the copy inside a hidden method. `dialog.js` takes the `<dialog>` that follows the trigger.
- Never let the graphic set the width of the payment `<fieldset>` (`min-width: min-content` by the browser's
  stylesheet; the mobile checkout grew to 420 px + padding): above the place order button `.copex-wl-scroll` has
  `width: 0; min-width: 100%`, reset inside `.copex-wl-dialog`, which sizes itself by its content. Never apply that
  pair globally — in a shrink-to-fit container of a project theme it collapses the graphic to nothing.
- Never inline `<script>` in templates (`Test/Unit/Templates/NoInlineScriptTest.php`).
- Never load Inter from Google Fonts (GDPR) — the TTFs are only used server-side.
- Never inline the official label SVG in HTML/JSON (~300 KB each because the QR code is ~700 paths); use the cached PNGs.
- Never let collection-loaded children fall back to raw attribute loading in the Resolver (N+1 on configurable PDPs):
  select `Attributes::ALL`, filter `garan_duration_years notnull`, pre-fill missing codes with null.

## TEST NOTES

- Run: `ddev exec 'XDEBUG_MODE=off php vendor/bin/phpunit --no-extensions -c dev/tests/unit/phpunit.xml.dist app/code/CopeX/WarrantyLabel/Test/Unit'`
  (`--no-extensions`: the project phpunit.xml.dist loads a broken Allure extension that turns a green run into exit 1).
- `GaranPngRendererTest` uses real GD + FreeType in a temporary directory; `FieldFitCheckerTest` reads the module TTFs.
- Generated factories (`GaranLabelDataFactory`) are mocked; `FieldFitChecker` is mocked in resolver/validator tests.
- `Templates/NoInlineScriptTest` scans every `view/frontend/templates/**/*.phtml`.
- Manual/E2E: QR scan from screen and email, keyboard-only dialog, screenshot protocol per placement × store view.
