# Security Policy

## Reporting a vulnerability

Please report security issues privately to **office@copex.io** rather than opening a public issue.

Include the module version, the Magento version, and enough detail to reproduce the problem. We will confirm
receipt and keep you informed while we work on a fix.

## Supported versions

Fixes are released for the latest `1.x` version. Older tags are not patched.

## What this module touches

Three areas are worth a reviewer's attention, because that is where the module handles untrusted input or
touches files:

- **Product attribute values** (`garan_brand`, `garan_model_identifier`, `garan_duration_years`,
  `garan_terms_url`) reach the storefront, the checkout and the order confirmation email. They are validated
  at render time as well as on save, because bulk editors and direct database writes bypass backend models.
- **The guarantee terms PDF** is resolved from a configured path below `pub/media` and attached to order
  confirmation emails. `Model/Email/TermsDocument` rejects paths that leave that directory and enforces a size
  limit.
- **Rendered label images** are written to `pub/media/copex_warranty_label/garan` and served as static files.

## What this module does not do

It stores no personal data, adds no controllers or routes, and makes no outbound network requests. The only
links it emits point at the European Commission's Your Europe pages and at the guarantee terms URL configured
on the product.
