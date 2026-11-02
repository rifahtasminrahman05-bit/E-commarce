# Functional Requirements, Roles, Stories, and Acceptance

## Customer-facing journeys

| Area | Behavior | Acceptance criteria |
|---|---|---|
| Browse | Home sections, category/subcategory/brand browse, search, availability/attribute/price filters, sorting, pagination | Only published and available-to-display products are returned; invalid filters return a documented validation error. |
| Product | SEO metadata, image gallery, specifications, variant/price/stock, reviews, recommendations | Variant selection is required where applicable; displayed price and stock are revalidated on cart/checkout. |
| Identity | Register/login/logout, email/phone verification, OTP, password reset, profile, sessions/tokens | OTP and reset tokens expire, are rate-limited, one-time, and never leak account existence unnecessarily. |
| Cart | Guest/authenticated persistent cart, variant-specific line, merge on login | Merge is transactional, deduplicates matching variant IDs, enforces quantity limits and current stock; conflicts are visible to the user. |
| Checkout | Address, zone/rate, coupon, COD/prepaid choice, summary | Server recalculates all totals; duplicate idempotency key cannot create a second order; stock and shipping are checked in the same transaction. |
| Account | Orders, addresses, tracking, wishlist, eligible reviews, returns/refunds, notifications/support | Object ownership is enforced on every request; unauthorized object access returns a non-disclosing 404/403 policy response. |

## Admin journeys

| Area | Behavior | Acceptance criteria |
|---|---|---|
| Dashboard | Revenue/order/customer/payment/courier indicators, charts, top products, low stock, recent orders | Metrics derive from real persisted data and document date/timezone and cancellation/refund treatment. |
| Catalog | Product/SKU/barcode/variants/media/attributes, categories, brands, import/export, SEO | Inputs validated; archive preserves historical order snapshots; bulk failures produce row-level reports and no partial silent success. |
| Inventory | Warehouses, available/reserved quantities, movements, transfers, damage/returns, alerts | No negative available stock; every adjustment has actor/reason; transfer is atomic across locations. |
| Orders | Search/filter, status timeline, invoice/slip, notes, packing, shipment, cancel/return | Separate order/payment/fulfillment state machines; transitions are authorized and audited. |
| Finance | Gateway config, transactions, refunds, reconciliation, failure queue | Secrets are masked; refunds enforce paid/refundable balance and provider idempotency. |
| Courier | Provider settings, bookings, consignments, tracking, COD and RTO reconciliation | Booking retries use idempotency; external failure remains visible and does not falsely mark shipped. |
| Marketing/CMS | Coupons, campaigns, flash sales, banners, sections, pages, menu, blog | Schedule/eligibility and consent rules are validated server-side; content is escaped and previewable. |
| Customer/support | Customer profile/history/segments/status, ticket/messages | Staff see only fields allowed by role; sensitive access is audited. |
| Administration | Staff, roles/permissions, MFA, sessions, settings, audit/activity logs | Least privilege; privileged changes require reauthentication/MFA where configured. |

## Lifecycle acceptance rules

### Order

`pending → confirmed → processing → packed → shipped → delivered`; allowed side paths include `cancelled`, `return_requested`, `returned`, `partially_fulfilled`, and `failed_delivery`. Exact permitted edges, role permissions, and reversal rules must be encoded as explicit transition rules. Shipped/delivered orders cannot use the ordinary cancellation path.

### Payment

`pending → processing → paid | failed | cancelled`; a paid payment can transition only through validated refund events to `partially_refunded` or `refunded`. Provider redirect alone never sets paid.

### Fulfillment

Track separately from order and payment: `unfulfilled`, `reserved`, `picking`, `packed`, `partially_shipped`, `shipped`, `delivered`, `failed`, `returned`.

## Business-rule decisions pending owner approval

- COD stock reservation lifetime and whether COD orders reserve at placement or confirmation.
- Prepaid reservation expiry and asynchronous expiry/release behavior.
- Return eligibility window, categories, condition rules, return shipping payer, and refund SLA.
- Partial shipment policy and whether one order can have multiple shipments.
- Guest review eligibility and verification method.
- Maximum cart quantities, coupon stacking, VAT/tax inclusion, rounding, free-shipping thresholds.
- OTP provider, required verification channel, and guest checkout policy.

## Failure acceptance

Every third-party timeout, invalid callback, stock conflict, coupon conflict, duplicate request, validation error, and failed queue job must leave persisted state consistent and return a structured actionable response. No integration failure may be presented as success.
