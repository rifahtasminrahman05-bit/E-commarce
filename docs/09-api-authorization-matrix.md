# API Authorization Matrix

Permission checks are enforced by Laravel Policies/Gates. Frontend route guards and hidden controls are usability features only, never security controls.

| Role | Storefront/customer | Catalog | Inventory | Orders/returns | Payments/refunds | Shipping | Marketing/CMS | Customers/support | Reports | Staff/settings/audit |
|---|---|---|---|---|---|---|---|---|---|---|
| Guest | Public browse, guest cart/quote/checkout, own guest tracking proof | Read published | None | Own guest order only with proof | Own payment initiation/status only | Public rates | Public campaigns | None | None | None |
| Customer | Own profile/address/cart/wishlist/orders/reviews/support | Read published | None | Own order and eligible return only | Own payment/refund status read | Own tracking | Public coupon redemption | Own notifications/tickets | None | None |
| Support agent | Own staff session | Read | Read summary only if permitted | Read/update approved service transitions, notes | Read sanitized state only | Read tracking | None | Read assigned customers/tickets | None | None |
| Catalog manager | None | Products/categories/brands/media/import/export | Read stock | Read orders only if needed for product history | None | None | CMS/SEO/banner rights if assigned | None | Product reports | None |
| Inventory operator | None | Read catalog | Adjust/transfer/movement/reservation rights | Read fulfillment lines | None | Read shipments | None | None | Inventory report | None |
| Order operator | None | Read catalog | Read availability | Confirm/process/pack/cancel within policy | Read status | Book/track shipment | Read coupon snapshot | Limited customer/order contact | Order report | None |
| Finance operator | None | None | None | Read orders | Verify/reconcile/refund within limits | COD reconciliation | Coupon usage read | None | Payment/refund reports | None |
| Delivery operator | None | Read order shipping snapshots | Read reserved quantities | Update fulfillment transitions | None | Book, track, reconcile COD/RTO | None | Read recipient delivery data | Courier report | None |
| Marketing editor | None | Read catalog | None | None | None | None | Campaign/coupon/banner/content scoped rights | Consent segments only | Marketing report | None |
| Analyst | None | Read aggregate catalog | Read aggregate | Read aggregate | Read aggregate | Read aggregate | Read aggregate | De-identified aggregates | Export allowed datasets | None |
| Administrator | Staff operations by assigned granular permission; no implicit unrestricted data access | By grant | By grant | By grant | By grant | By grant | By grant | By grant | By grant | Staff/role/settings/audit permissions |
| System integration | Provider-specific signed webhook/actions only | None | None | Only associated order callback event | Provider scoped callbacks | Courier scoped callbacks | None | None | None | No interactive administration |

## Authorization policies

- Every customer object query is scoped to authenticated owner ID or a one-time, expiring guest proof; never accept owner IDs from the client as authorization.
- Admin list/detail endpoints require module-specific view permission; every mutation requires a separate action permission.
- Refund amount limits, inventory adjustment limits, export access, staff role changes, and secret settings may require elevated permission plus MFA/reauthentication.
- Staff cannot grant permissions they do not hold; prevent self-escalation and last-super-admin lockout.
- Support/analyst roles use field-level minimization and redact phone/address unless task requires it.
- Service accounts are provider-scoped, key-rotatable, rate-limited, and cannot use browser/admin routes.
- Policies return non-disclosing 404 for sensitive foreign object identifiers when appropriate.

## Audit-required actions

Role/permission changes, login/MFA recovery, customer data export, inventory adjustments/transfers, order state transitions, manual payment verification, refund, provider configuration, coupon edits, address/admin data access exports, and audit-log access.

## Tests

For each role, test allowed and denied list/detail/read/write paths; customer A vs customer B; guessed IDs (IDOR/BOLA); revoked tokens; inactive staff; privilege escalation; object ownership during nested routes; and provider callback impersonation.
