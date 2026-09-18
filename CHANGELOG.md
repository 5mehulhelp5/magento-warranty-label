# Changelog

All notable changes to `copex/module-warranty-label` are documented in this file. Format follows
[Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/); versioning follows
[Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html).

## [1.2.0] – 2026-09-18

Luma compatibility, checked placement by placement in a Luma and a Blank storefront at 1280 px and 375 px,
including a placed order, the success page and the confirmation email.

### Fixed

- **The notice and the GARAN summary never appeared in the Luma checkout.** Both hung on
  `sidebar > summary > before-place-order`, a node `Magento_Checkout` does not define. A jsLayout child of a node
  without a component is created but never attached to a parent, so it never renders, and nothing reports it. They
  now use
  `payments-list > before-place-order`, the region every payment method renders above its place order button and
  the one the checkout agreements use.
- The nested display opened the wrong dialog there: Magento renders that region once per payment method, which
  repeated the dialog ids, and the id lookup found the copy of a payment method that is not displayed. The dialog
  next to the trigger wins now, and repeated ids are made unique.
- A direct notice widened the mobile checkout by 75 px. Magento's payment method templates wrap their content in a
  `<fieldset>`, whose `min-width` is `min-content` by the browser's own stylesheet, so the 420 px graphic set the
  width of the page. Above the place order button the scroll container no longer contributes to the intrinsic
  width of its ancestors.
- The full GARAN label (270 px) did not fit beside the thumbnail of a summary item: 183 px on a phone. It starts at
  the left edge of the item now, below the thumbnail.
- The header notice sat squeezed between Luma's floated logo and search; it takes a row of its own.
- `Test/bootstrap.php` declares `Magento\Catalog\Model\ResourceModel\Product\CollectionFactory` like the other
  two generated factories. Without it `AuditCommandTest` failed wherever the autoloader does not cover
  `generated/code`. The three guards now autoload before they declare, so a real generated class wins as the
  comment always said.

### Changed

- **Upgrade note: themes that do provide `sidebar > summary > before-place-order` lose the notice in that place.**
  Register the two components there again in the project theme; the README section *Themes* shows the pattern
  with `afterMethods`, the node name is the only difference.
- **Check every payment method after the update.** The region is rendered by the template of the payment method; one
  that leaves it out shows no notice (and no checkout agreements). See `docs/COMPLIANCE-DE.md`, section 4.

### Added

- **Setup guide in the user manual** (`docs/DE.md`, `docs/EN.md`, new chapter 3): ten steps from choosing the scope to
  going live, with a check list per step — including the one that matters most after this release, selecting every
  payment method once. New sections on themes (2.3) and on a notice missing in the checkout (8.6, 8.7); the chapters
  after 2 move up by one.
- Screenshots: the three configuration groups separately and without the "Module Version" field removed in 1.1.0,
  plus the Luma header, checkout (direct and nested) and the label on the summary item.
- `Test/Unit/Layout/CheckoutLayoutTest` compares the checkout layout with the one of `Magento_Checkout`: every
  parent of a component of this module has to exist there and carry a component.

## [1.1.4] – 2026-09-17

### Fixed

- The workflows listened on `master` only and therefore never ran: GitHub names the default branch `main`.
  Both now trigger on either name.

## [1.1.3] – 2026-09-17

### Added

- Packagist metadata: keywords, homepage, authors and the `support` links pointing at
  https://github.com/CopeX/magento-warranty-label — Packagist reads these from the tag, so they only reach the
  package page with a release that carries them.
- `SECURITY.md` with a private reporting address, plus a short note on where the module handles untrusted
  input: product attribute values, the guarantee terms PDF path, and the rendered label images.
- Build and licence badges in the README.

## [1.1.2] – 2026-09-17

### Fixed

- The 1.1.1 tag carried `.phpunit.cache/test-results`, swept in by a careless `git add -A`. The file is gone
  and ignored; 1.1.1 is left where it is rather than moved, so nothing that already resolved it changes.

## [1.1.1] – 2026-09-17

### Changed

- **CI moves from GitLab to GitHub.** `.gitlab-ci.yml` is gone; with it goes the documentation sync to
  docs.copex.io, which needs a new home.
- Two workflows instead of one pipeline: `lint.yml` runs for everyone including forks, because
  `magento/magento-coding-standard` is on Packagist; `tests.yml` needs a `MAGENTO_COMPOSER_AUTH` secret for
  repo.magento.com and is skipped on forks rather than failing them with a misleading red cross.

### Added

- `phpunit.xml` and `Test/bootstrap.php`, so the suite runs from the package itself instead of borrowing a
  Magento installation's configuration. The bootstrap declares the two `*Factory` classes the tests mock:
  Magento generates those during `setup:di:compile`, and a plain Composer autoloader has no generated/code.
  A real installation that already provides them wins.
- `phpunit/phpunit` as a dev dependency.

## [1.1.0] – 2026-09-17

### Changed

- **Licence is now MIT** (`LICENSE` replaces `license.md`). The bundled EU artwork and the Inter fonts keep
  their own terms, which `LICENSE` names explicitly: the Commission publishes the notice and label files
  without a redistribution licence, so they are not covered by the MIT grant.
- **Dropped the `copex/module-core` dependency**, together with the module version field it rendered in the
  admin section. The module now installs from Magento packages alone, which is what an open-source package
  needs — a required proprietary package from a private registry would have made it unusable.

### Removed

- Customer-specific traces ahead of publication: the store code in the audit test, the shop name in the
  compliance document, the named third-party extensions in `AGENTS.md`, and the catalogue product name in two
  screenshots.

## [1.0.3] – 2026-09-16

### Added

- `Setup/Uninstall.php`, so `bin/magento module:uninstall CopeX_WarrantyLabel --remove-data` also drops the
  module's configuration and the cached label images. The uploaded guarantee terms PDF is kept on purpose.
  The product attributes and their group were already reverted by the install patch; without that revert an
  installation that loses the files keeps attribute rows whose `backend_model` names missing classes, and then
  fails on every product page.
- Both manuals and the README document the uninstall and why `--remove-data` is not optional.

## [1.0.2] – 2026-09-16

### Added

- Screenshots in the operator manual: the configuration section, the GARAN product attributes, the nested
  notice and label with their dialogs, the checkout and the order confirmation. All of them are element
  captures of the module's own output, so no storefront theme or shop data is carried into the manual.

## [1.0.1] – 2026-09-16

### Added

- Operator manual in German and English (`docs/DE.md`, `docs/EN.md`) plus `docs/module.conf`, so the shared
  CopeX documentation pipeline publishes the module on docs.copex.io. Without `module.conf` the sync job
  aborts before it starts.

## [1.0.0] – 2026-09-16

### Added

- EU harmonised notice on the legal guarantee of conformity (Implementing Regulation (EU) 2025/1960, Annex I):
  official colour graphic for all 24 EU languages, label language per store view, display mode per placement
  (header, footer, category, search, cart, checkout before "Place Order", success page, order confirmation email),
  accessible nested display (button → native dialog), clickable Your Europe link, minimum width 420 px.
- EU GARAN label (Annex II): product attributes `garan_brand`, `garan_model_identifier`, `garan_duration_years`,
  `garan_terms_url` on simple products; render-time validation incl. metric field-fit checks (Inter TTF);
  cached PNG rendering on backgrounds rasterised from the official label files; product page (incl. variant
  switch without extra queries per child), checkout (per item and before "Place Order"), success page and order
  confirmation email; order item snapshot `sales_order_item.copex_garan_label`.
- Guarantee terms attachment: one PDF per store view is attached to the order confirmation of every order that
  contains a product with a GARAN label, so the guarantee statement reaches the consumer on a durable medium
  (Art. 17(2) Directive (EU) 2019/771, § 9a (3) KSchG, § 479 (2) BGB; a link is not sufficient, CJEU C-49/11).
  Configured in the module options, implemented without third-party email modules.
- CLI `copex:warranty-label:audit` listing products with incomplete or invalid GARAN data.
- German translations (`de_DE`, `de_AT`).
- Module version shown in the admin configuration section (`CopeX_Core`).
