# Warranty Label
# Extension for Magento 2
## User Manual

CopeX GmbH
Web: https://copex.io
Email: office@copex.io

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Requirements](#2-requirements)
3. [Setting up step by step](#3-setting-up-step-by-step)
4. [Configuration](#4-configuration)
5. [Product data for the GARAN label](#5-product-data-for-the-garan-label)
6. [Guarantee terms as an attachment](#6-guarantee-terms-as-an-attachment)
7. [Data audit](#7-data-audit)
8. [Troubleshooting](#8-troubleshooting)
9. [Obligations that stay with the merchant](#9-obligations-that-stay-with-the-merchant)

---

## 1 Introduction

From **27 September 2026**, Implementing Regulation **(EU) 2025/1960**, together with Directive **(EU) 2024/825**, requires two new pieces of information in online retail. This module implements both:

- **The legal guarantee notice** (Annex I of the Regulation) is an official graphic that every shop selling goods to consumers must display. It informs customers about their statutory guarantee rights and is mandatory whether or not a producer offers an additional guarantee.
- **The GARAN label** (Annex II) concerns individual products only: those for which the producer grants a free durability guarantee covering the entire good for more than two years.

Both graphics are prescribed by law. The module ships them in all 24 EU languages and never alters them — neither colours nor type, spacing or QR code. On the GARAN label, only the four intended fields are filled in: brand, model identifier, duration and the link to the guarantee terms.

The module is **switched off** after installation. Nothing appears in the shop until you deliberately enable it.

> This manual describes how to operate the module. It is not legal advice. Which of your products carry a qualifying producer guarantee, and how the display is to be assessed legally, is for you or your legal counsel to decide.

---

## 2 Requirements

| Component | Version |
|---|---|
| Magento 2 Open Source / Adobe Commerce | 2.4.x |
| PHP | 8.2 or newer |
| PHP extension GD | with FreeType support |

The GD extension **must be compiled with FreeType**. The GARAN label is produced server-side by drawing the text fields onto the official template. Without FreeType the module cannot render type and produces no label. To check your installation, run `php -i | grep -i freetype`.

The legal guarantee notice works without GD, because it ships as a finished graphic.

### 2.1 Installation

```bash
composer require copex/module-warranty-label
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy   # production mode only
bin/magento cache:flush
```

Nothing is visible in the shop after the installation. Chapter 3 describes the setup.

### 2.2 Uninstalling

```bash
bin/magento module:uninstall CopeX_WarrantyLabel --remove-data
```

**The `--remove-data` flag matters.** Without it the four GARAN attributes stay in the database, and their
stored backend model names classes of this module. An installation that loses the files but keeps those rows
then answers **every product page with an error**, because loading product attributes tries to instantiate a
class that is no longer there.

Removed with the flag: the four attributes and their attribute group, every setting below
`copex_warrantylabel`, the `sales_order_item.copex_garan_label` column, and the cached label images below
`pub/media/copex_warranty_label/garan`.

**The uploaded guarantee terms PDF is kept** (`pub/media/copex_warranty_label/terms`). That file is your own
document and may still be linked from past orders; delete it by hand when you no longer need it.

### 2.3 Themes

| Theme | Status |
|---|---|
| **Luma** and **Blank**, and themes derived from them | Fully supported. Every placement was checked in both themes on the desktop (1280 px) and on a phone (375 px), including a placed order, the success page and the email. |
| **Hyvä** | Partial. The notice in *Direct* mode works in the header, footer, category, search, cart and on the success page. The nested display, the variant switch on the product page and the checkout rely on Luma technology (RequireJS, Knockout) and are not implemented for Hyvä yet. This status has not been accepted in a Hyvä installation; check every placement there following steps 4 and 5. |
| **Custom checkout** (one-step checkout, place-order button outside the payment method) | Notice and GARAN list sit in Magento's area above the place-order button of the selected payment method — the same place as the checkout agreements. If your checkout does not render that area, your agency has to move the two components in the project theme. The pattern is in the module's `README.md`, section *Themes*. |

Step 5 of the setup shows within minutes whether your checkout is affected.

---

## 3 Setting up step by step

This chapter walks through the complete setup once. Steps 1 to 5 concern the legal guarantee notice and therefore **every shop**. You need steps 6 to 9 only if you sell products with a qualifying producer guarantee (see section 5.1). Step 10 completes the setup.

Set the module up in a test environment first and transfer the settings to the live shop afterwards. Allow about half an hour for the notice, plus the time for maintaining data if you use the GARAN label.

### Step 1: Choose the scope

Open *Stores → Configuration → Sales → EU Guarantee Notice & GARAN Label*.

Every field of the module is scoped **per store view**. The **Scope** switcher sits at the top left:

- In **Default Config** you set what all storefronts share — usually the main switch and the display modes.
- In the individual **store view** you set what differs — usually the language, the guarantee terms and, if needed, the button text.

A field with **Use Default** or **Use system value** ticked inherits the value of the level above. Untick it to edit the field.

> If you run store views for marketplaces (Amazon, eBay …) or pure B2B storefronts, leave the module switched off there. The obligation concerns the sale of goods to consumers.

### Step 2: Switch the notice on

Open the group **General Settings**.

![The General Settings group](screenshots/01a_config_general.png)

1. Set **Enable Module** to *Yes*.
2. For **Label Language**, choose the language the content of this storefront is written in. *Automatic* is enough when the locale of the store view matches that language. For an English storefront on a German locale, choose *English* explicitly.
3. Leave **Nested Display Button Text** and **Notice Alternative Text** empty for now. The module then uses the translated defaults, in English "Your legal guarantee rights". Enter a text only if your shop uses a different wording.
4. Leave **Minimum Notice Width** at *420*.
5. Check **Product Types Without Legal Guarantee Notice**. If you sell vouchers or downloads through a product type of their own that is not highlighted in the list, highlight it as well (Ctrl-click).
6. Click **Save Config**.

### Step 3: Decide on the placements

Open the group **Legal Guarantee Notice Placements**.

![The Legal Guarantee Notice Placements group](screenshots/01b_config_notice_placements.png)

Each row is a place in the shop, and each place has one of the three modes *Off*, *Direct* and *Nested* (see section 4.2). The defaults are a sensible starting point: nested wherever the graphic would break the layout, direct where the customer stands immediately before or after the order.

| Placement | Where the notice appears in Luma |
|---|---|
| Header | In a row of its own below the logo, on every page |
| Footer | At the end of the footer, on every page |
| Category Page | Below the product list |
| Search Results | Below the search results |
| Shopping Cart | In the summary, below the totals |
| Checkout (before Place Order) | In the *Payment* step, inside the selected payment method directly above the place-order button |
| Checkout Success Page | Below the order confirmation |
| Order Confirmation Email | Below the item list of the email |

![The notice in the Luma header, nested](screenshots/12_header_notice.png)

Header and footer show the same notice on every page. If one of the two is enough for you, set the other to *Off*. **Which placements you need, and whether the nested display is sufficient for you, is a legal question** — agree the selection with your legal counsel and record the result.

Click **Save Config**.

### Step 4: Flush the cache and check the notice in the shop

Flush the cache under *System → Cache Management* (**Flush Magento Cache**). Without this step, pages that are already cached do not show the notice.

Then open the storefront and go through the places — once on the desktop, once on a phone:

- [ ] Home page: button in the header and in the footer, a click opens the complete graphic, Escape or *Close* closes it again
- [ ] a category page and a search results page
- [ ] the cart with at least one item
- [ ] the language of the graphic matches the storefront
- [ ] the QR code can be scanned from the screen with a phone and leads to the EU page
- [ ] the link below the graphic leads to the same page

![The dialog shows the complete official graphic](screenshots/06_notice_dialog.png)

### Step 5: Check the checkout — with every payment method

Put an item in the cart and proceed to the *Payment* step. The notice sits inside the selected payment method, directly above the place-order button and next to the checkout agreements.

![The notice in the Luma checkout, directly above the place-order button](screenshots/10_checkout_notice_direct.png)

**Select every payment method of your shop once** and check that the notice appears there. Each payment method renders the area above the place-order button itself. If the module of a payment provider leaves it out, that payment method lacks the notice **and** the checkout agreements. In that case contact your agency (see section 8.6).

On a phone the checkout is narrower than the minimum width of the graphic. In *Direct* mode the graphic can be scrolled sideways there; it is never shrunk or cropped. If you do not like that, set the *Checkout* placement to *Nested*.

**If you have no products with a qualifying producer guarantee, continue with step 9.**

### Step 6: Switch the GARAN label on

Open the group **EU GARAN Label**.

![The EU GARAN Label group](screenshots/01c_config_garan.png)

1. Set **Enable GARAN Label** to *Yes*.
2. Keep the four display modes at their defaults for now: *Nested* on the product page, *Direct* in the checkout, on the success page and in the email.
3. Click **Save Config**.

This changes nothing in the shop yet. A label appears only on products whose data you maintain in the next step.

### Step 7: Maintain the product data

Under *Catalog → Products*, open a **simple product** and there the group **EU GARAN Guarantee**.

![The four GARAN attributes on the simple product](screenshots/02_product_garan_attributes.png)

| Field | Example | Note |
|---|---|---|
| GARAN Brand | `Musterwerk` | The brand as it should appear on the label |
| GARAN Model Identifier | `MW-2000-S` | The producer's model identifier of exactly this variant |
| GARAN Guarantee Duration (Years) | `5` | Whole years from 3 to 99. For half years see section 5.3 |
| GARAN Guarantee Terms URL | `https://www.example.com/guarantee` | Complete address. The field is scoped per store view: switch the store view at the top left to store one address per language |

Save the product. If Magento rejects the brand or the model identifier, the text is too wide for the label field (see section 8.2).

For **configurable products**, maintain the values on the individual variants, not on the parent. For many products, maintain the four attributes through the import; the attribute codes are `garan_brand`, `garan_model_identifier`, `garan_duration_years` and `garan_terms_url`.

Then open the product in the storefront. The label sits below the price as a button; a click shows it completely. On a configurable product it appears only after a variant has been selected.

![On the product page a button opens the label](screenshots/03_product_garan_trigger.png)

### Step 8: Upload the guarantee terms

The guarantee terms have to reach the customer as a file; a link is not enough (see chapter 6). In the group **EU GARAN Label**:

1. Switch to the **store view** the document applies to at the top left. There is one document per store view, matching its language.
2. Set **Attach Guarantee Terms to the Order Confirmation** to *Yes*.
3. Choose your PDF file under **Guarantee Terms File (PDF)**.
4. Under **Attachment File Name**, enter the name the customer should see, for example `Guarantee-terms.pdf`.
5. Click **Save Config**. The file is uploaded only then; afterwards its path is shown below the field.

The document itself has to name the goods it covers (see section 6.2).

### Step 9: Place a test order

Order once to the end in the test environment — with the GARAN label ideally one product with and one without a label.

- [ ] In the checkout the label sits on the item in the order summary and collected above the place-order button
- [ ] The success page shows the notice and the labels of the ordered items
- [ ] The order confirmation contains the notice, the label with both links per item, and the PDF file as an attachment
- [ ] An order **without** a labelled product contains the notice but no attachment

![The label on the item in the order summary](screenshots/11_checkout_item_label.png)

![The GARAN labels above the place-order button, here on a phone](screenshots/07_checkout_garan_summary.png)

![The order confirmation with label, links and the note about the attachment](screenshots/09_email_garan_section.png)

### Step 10: Audit the data and go live

Have your agency or administrator run the data audit, once per store view:

```bash
bin/magento copex:warranty-label:audit --store=<store view code>
```

The list names every product that shows **no** label because of incomplete or invalid data, with the reason (see section 8.1). An empty list means all maintained products are complete.

For the live shop:

1. Transfer the settings from steps 2, 3 and 6. Your agency can do that from the command line; the configuration path is shown below every field in the admin:

   ```bash
   bin/magento config:set --scope=stores --scope-code=<store view code> copex_warrantylabel/general/enabled 1
   bin/magento config:set --scope=stores --scope-code=<store view code> copex_warrantylabel/notice_placement/footer nested
   ```

   The values of the display modes are `off`, `direct` and `nested`.
2. **Upload the PDF file again in the admin of the live shop.** The command line cannot set file fields (see section 6.1).
3. Flush the cache.
4. Repeat the checks from steps 4, 5 and 9 in the live shop.

The obligation applies from **27 September 2026**. The main switch **Enable Module** switches every output of the module off again at once for a scope, should something be wrong in the live shop.

---

## 4 Configuration

This chapter describes every field in detail. The order of the setup is in chapter 3.

All settings live under *Stores → Configuration → Sales → EU Guarantee Notice & GARAN Label*. The section is scoped **per store view**, so each storefront can have its own language and its own display modes.

![The configuration section with all three groups](screenshots/01_config_copex_warrantylabel.png)

### 4.1 General settings

- **Enable Module** — the main switch. Set to *No*, neither notice nor label appears in this scope, whatever the other settings say. *Default: No.*
- **Label Language** — selects the official language version of the notice and the target of the link. *Automatic (from store locale)* derives the language from the store locale. Set it explicitly when the locale does not match the language of your shop content — for example an English storefront that technically runs on `de_AT`. If no language can be determined, English is used.
- **Nested Display Button Text** — the caption of the button that opens the notice in nested mode. Leave empty for the translated default "Your legal guarantee rights".
- **Notice Alternative Text** — the alternative text of the graphic for screen readers and for email clients that block images. Leave empty for the translated default.
- **Minimum Notice Width (CSS px)** — the graphic is never rendered narrower than this, so its QR code stays scannable. *Default: 420.* Do not lower it without good reason: below that width the QR code falls under the required minimum size.
- **Product Types Without Legal Guarantee Notice** — product types that are not goods in the sense of the legal guarantee, such as vouchers or downloads. *Default: Virtual, Downloadable, Gift Card, MageWorx Gift Cards.* In the cart, the checkout and the email, the notice appears only if at least one other item is present.

### 4.2 Legal guarantee notice placements

For each placement you choose one of three modes:

| Mode | Meaning |
|---|---|
| **Off** | No output at this position. |
| **Direct (complete graphic)** | The complete official graphic sits directly on the page. |
| **Nested (button opens dialog)** | A button opens the graphic in a dialog on the first click. |

The nested display is permitted by the European Commission's guidelines. A stricter reading requires the direct display. Because this is a legal question, the module lets you decide per placement.

| Placement | Default |
|---|---|
| Header | Nested |
| Footer | Nested |
| Category Page | Nested |
| Search Results | Nested |
| Shopping Cart | Nested |
| Checkout (before Place Order) | Direct |
| Checkout Success Page | Direct |
| Order Confirmation Email | Direct |

Two notes on choosing the mode:

- **Narrow containers speak for "Nested".** Where the available space is narrower than the configured minimum width, the direct graphic is not shrunk but can be scrolled sideways — it is complete but looks cut off. That mainly concerns the checkout on a phone. In the dialog the graphic appears at full size.
- **Emails have no dialogs.** If the email placement is set to *Nested*, it is rendered like *Direct*.

The dialog is fully keyboard operable: Enter or Space open it, Escape closes it, and focus returns to the button afterwards.

![In nested mode a button opens the notice](screenshots/05_notice_trigger.png)

![The dialog shows the complete official graphic](screenshots/06_notice_dialog.png)

### 4.3 GARAN label

- **Enable GARAN Label** — releases the label. *Default: No.* While it is off, the product attributes are kept but displayed nowhere.
- **Product Page** — *Default: Nested.*
- **Checkout (before Place Order)** — *Default: Direct.* The label appears in two places: on the individual item in the order summary, and collected inside the selected payment method directly above the place-order button. The second position matters on mobile, where the order summary is collapsed.
- **Checkout Success Page** — *Default: Direct.*
- **Order Confirmation Email** — *Default: Direct.*

The remaining fields of the group concern the attachment and are described in chapter 6.

![Notice and GARAN labels in the checkout, here both nested](screenshots/08_checkout_notice.png)

![The GARAN label in the checkout, directly above the place-order button](screenshots/07_checkout_garan_summary.png)

---

## 5 Product data for the GARAN label

The module creates no guarantee data. It only displays what you maintain.

On installation, the group **EU GARAN Guarantee** is added to every attribute set, holding four attributes. They apply to **simple products only**, because the model identifier belongs to the specific variant rather than to the configurable parent.

| Attribute | Scope | Meaning |
|---|---|---|
| **GARAN Brand** | global | The brand as printed on the label. |
| **GARAN Model Identifier** | global | The model identifier as printed on the label. |
| **GARAN Guarantee Duration (Years)** | global | Whole or half years, more than 2, e.g. `3` or `4,5`. Leave empty when there is no free producer guarantee on the whole product. |
| **GARAN Guarantee Terms URL** | store view | Full `http://` or `https://` address of the guarantee terms in the language of the storefront. |

A label appears only when **all four values are present and valid**. If one is missing, the module displays nothing — it never shows an incomplete label.

![The four GARAN attributes on the simple product](screenshots/02_product_garan_attributes.png)

![On the product page a button opens the label](screenshots/03_product_garan_trigger.png)

![The complete GARAN label in the dialog](screenshots/04_product_garan_label.png)

### 5.1 When a product qualifies at all

The GARAN label is not a marketing device but mandatory information for a narrowly defined case. The guarantee must

- come from the **producer** (not the retailer),
- be **free of charge** for the consumer,
- cover the **entire good** (not just individual components) and
- run for **more than two years**.

If even one of these does not hold, no label may be set. Leave the duration empty in that case.

### 5.2 Variants

On configurable products the values belong to the selected variant. The module therefore shows

- **no** label on the configurable parent,
- **no** label while no variant has been selected,
- and swaps the label as soon as the customer selects a different variant.

On bundle products all contained items are taken into account.

### 5.3 Half guarantee years

The Regulation permits half years. At the official type size, however, only whole years from 3 to 99 and the value `7,5` fit into the field provided. Other half-year values such as `2,5` or `4,5` can be saved, but produce **no label**, and the data audit reports the reason `duration_does_not_fit`.

This is deliberate. The alternative would be to shrink the type or move the calendar icon — both breach the design rules. A clarification has been requested from the European Commission.

### 5.4 Ordered goods keep their label

When an order is placed, the module stores the label data on the order item. Changing an attribute later does **not change existing orders**. A re-sent document still shows the values that applied at the time of purchase.

---

## 6 Guarantee terms as an attachment

A link to a website is not legally sufficient. The guarantee statement must reach the consumer **on a durable medium**, at the latest at delivery (Art. 17(2) of Directive (EU) 2019/771, § 9a (3) KSchG, § 479 (2) BGB). The Court of Justice of the European Union has ruled that a website merely referred to does not meet this requirement (Case C-49/11).

The module therefore attaches a PDF file to the order confirmation — only for orders containing at least one product with a GARAN label. No additional extension is required.

### 6.1 Setting it up

- **Attach Guarantee Terms to the Order Confirmation** — enables the attachment. *Default: No.*
- **Guarantee Terms File (PDF)** — the file. One document per store view.
- **Attachment File Name** — the name the customer sees in the email. Leave empty for the name of the uploaded file. *Default: `Garantiebedingungen.pdf`.*

**The file must be uploaded in the admin.** The command `bin/magento config:set` cannot write file fields, because a real upload is expected behind the scenes. A value set from the command line has no effect.

![The order confirmation with label, links and the note about the attachment](screenshots/09_email_garan_section.png)

### 6.2 One document for several products

One document per store view is sufficient as long as it states which goods it covers — for example "applies to all products of brand X carrying the GARAN label". If you need different terms for different brands, combine them in one document or separate the brands into their own store views.

The attachment does not replace the link on the label. Both are required: the link for the information before purchase, the attachment for the durable medium.

---

## 7 Data audit

The following command lists all simple products whose GARAN data is incomplete or invalid and which therefore show no label:

```bash
bin/magento copex:warranty-label:audit
```

| Option | Meaning |
|---|---|
| `--store=<ID or code>` | Checks the values in this store view. Without it, the admin values are checked. |
| `--limit=<count>` | Stops after this many problematic products. `0` means no limit. |

The output is a table of SKU, product ID and reasons, followed by a summary of how many products were checked.

Because the guarantee terms address is scoped per store view, a run per storefront is worthwhile:

```bash
bin/magento copex:warranty-label:audit --store=at
```

Run the command after every bulk import. Tools that write directly into the database bypass validation on save — the module therefore validates again at display time, so faulty data never produces a wrong label, but equally produces no label at all, silently.

---

## 8 Troubleshooting

### 8.1 No GARAN label appears

The audit from chapter 7 names a reason for each product:

| Reason | Meaning |
|---|---|
| `missing_brand` | The brand is missing. |
| `missing_model_identifier` | The model identifier is missing. |
| `missing_duration` | The duration is missing. |
| `missing_terms_url` | The link to the guarantee terms is missing. |
| `invalid_duration` | The duration is not a number above 2 in steps of 0.5, or it exceeds 99. |
| `duration_does_not_fit` | The duration is permissible but does not fit the label field (see section 5.3). |
| `invalid_terms_url` | The link is not a complete `http://` or `https://` address. |
| `too_long` | Brand or model identifier are too wide for the field provided. |

If the audit reports nothing and still no label appears, check in order: is *Enable Module* active? Is *Enable GARAN Label* active? Is the placement set to something other than *Off*? Is this a simple product, or has a variant been selected?

### 8.2 Brand or model identifier are rejected

The label fields have a fixed width. The module measures the text in the official typeface and rejects values that are too wide instead of shrinking the type. Shorten the entry — usually it is enough to drop additions such as the product line.

### 8.3 The notice looks cut off

The container is narrower than the configured minimum width, typically in the checkout on a phone. The graphic is complete and can be scrolled sideways. Set this placement to *Nested* if you want to avoid that; in the dialog the graphic appears at full size. Do not reduce the minimum width, or the QR code becomes too small.

### 8.4 The email carries no attachment

The attachment is sent only with orders containing at least one product with a GARAN label. Check as well whether *Attach Guarantee Terms* is active in the right store view and whether a file has actually been stored there — a value set from the command line has no effect (see section 6.1).

### 8.5 The language does not match the shop

The language of the notice follows the *Label Language* setting, not the locale. When it is set to *Automatic*, the store view locale is used. For an English storefront running on a German locale, set the language explicitly.

### 8.6 The notice is missing in the checkout

Check in this order:

1. **Is the *Checkout* placement set to *Off*?** See section 4.2.
2. **Does the cart contain only items without a legal guarantee?** For a cart consisting solely of vouchers, downloads or other excluded product types, the notice is deliberately not shown.
3. **Is it missing for one payment method only?** Then the module of that payment provider does not render the area above the place-order button. You can tell because the checkout agreements are missing for that payment method as well. Your agency can extend the template of the payment method or move the notice to another place in the checkout.
4. **Is it missing for all payment methods?** Then your shop uses a checkout that does not know this area — a one-step checkout, for example, or a theme with the place-order button in the sidebar. Your agency moves the two components in the project theme; the pattern is in the module's `README.md`, section *Themes*.
5. **Has the cache been flushed?**

### 8.7 Nothing is visible in the shop after activation

Flush the cache under *System → Cache Management*. Also check that you made the setting in the right scope: a value in *Default Config* has no effect when the store view carries a differing value. In production mode the static files additionally have to be deployed after the installation (see section 2.1).

---

## 9 Obligations that stay with the merchant

The module presents the prescribed information. It does not judge whether that information is correct. The following remain your responsibility:

1. **Selecting the products.** Which goods carry a qualifying producer guarantee is for you to decide, based on the producer's commitment.
2. **Maintaining the data.** Brand, model identifier, duration and terms must be current. When a guarantee commitment ends, remove the values.
3. **The content of the guarantee terms.** The stored PDF is your document; the module checks neither its content nor its completeness.
4. **Choosing between direct and nested display.** This is a legal question to settle with your legal counsel.
5. **Marketplaces.** On Amazon, eBay and comparable channels the display obligation lies with the marketplace. Switch the module off for store views serving such channels.

A complete mapping of the legal requirements to their implementation ships with the module as the file `docs/COMPLIANCE-DE.md` (German). That document is written for your legal counsel.
