# REST API Specification (Draft)

## Contract conventions

- Base: `/api/v1`; HTTPS JSON; customer routes and `/admin` routes are separate groups.
- Success: `{"success":true,"message":"Request successful","data":{},"meta":{}}`.
- Error: `{"success":false,"message":"Validation failed","errors":{"field":["Reason"]},"code":"VALIDATION_ERROR","request_id":"..."}`.
- Pagination: `?page=1&per_page=24`; bounded page size (default 24, maximum 100); `meta.pagination` reports page, per-page, total, last-page.
- Filters and sort fields are allow-listed. Money is a decimal string with currency code. Times are ISO-8601 UTC.
- Auth: Sanctum secure cookie/session for same-site web or revocable bearer tokens for mobile. Never accept frontend price, permission, stock, payment status, or discount as authoritative.
- Sensitive write endpoints require CSRF where cookie-authenticated and `Idempotency-Key` where indicated. Rate limits are configurable by route/user/IP; initial defaults: auth 5/min/IP, OTP 3/10 min/phone, public read 120/min/IP, customer write 30/min/user, admin write 60/min/staff.

## Public and customer endpoint inventory

| Method | Path | Auth | Request/validation summary | Response / status | Idempotency |
|---|---|---|---|---|---|
| GET | `/health` | none, restricted network | no body | liveness only; 200/503 | no |
| GET | `/storefront/home` | optional | locale `bn|en` | curated sections; 200 | no |
| GET | `/categories` | optional | parent, locale, active | paginated tree; 200/422 | no |
| GET | `/brands` | optional | search, page | paginated; 200/422 | no |
| GET | `/products` | optional | q/category/brand/min/max/available/attributes/sort/page | product cards; 200/422 | no |
| GET | `/products/{slug}` | optional | locale | product detail, variants, SEO; 200/404 | no |
| GET | `/search/suggestions` | optional | q length 2..80 | suggestions; 200/422 | no |
| POST | `/auth/register` | none | name, email or phone, password, locale | user + verification state; 201/422/429 | yes |
| POST | `/auth/login` | none | email/phone + password | session/token; 200/401/429 | no |
| POST | `/auth/logout` | customer | current token/session | revoked; 204 | no |
| POST | `/auth/otp/request` | none | phone, purpose | accepted without account enumeration; 202/422/429 | yes |
| POST | `/auth/otp/verify` | none | phone, purpose, code | short-lived verification result; 200/422/429 | yes |
| POST | `/auth/password/forgot` | none | email/phone | generic accepted response; 202/429 | yes |
| POST | `/auth/password/reset` | none | reset token, new password | result; 200/422/429 | yes |
| GET/PATCH | `/me` | customer | profile allow-list | profile; 200/422 | PATCH yes |
| GET/POST | `/me/addresses` | customer | address fields, Bangladesh area IDs | address list/created; 200/201/422 | POST yes |
| PATCH/DELETE | `/me/addresses/{id}` | customer | owned ID; partial fields | 200/204/404/422 | mutation yes |
| GET/POST | `/me/devices` | customer | platform, push token | devices; 200/201/422 | POST yes |
| DELETE | `/me/devices/{id}` | customer | own device | 204/404 | yes |
| GET/POST | `/cart` | optional/guest token | cart lines / current version | current server quote; 200/201/422 | write yes |
| POST | `/cart/items` | optional | variant_id, quantity 1..99 | recalculated cart; 201/409/422 | yes |
| PATCH/DELETE | `/cart/items/{id}` | cart owner | quantity or remove | recalculated/204; 200/404/409 | yes |
| POST | `/cart/merge` | customer | guest cart token | merged cart and conflicts; 200/409 | yes |
| GET/POST/DELETE | `/wishlist/items[/{productId}]` | customer; guest local allowed policy pending | product ID | wishlist; 200/201/204/404 | writes yes |
| POST | `/checkout/quote` | optional | address/zone, coupon, payment method | server quote + expiry; 200/409/422 | yes |
| POST | `/checkout/orders` | optional/customer | quote ID, address snapshot/reference, payment method | order + payment action; 201/409/422 | **required** |
| GET | `/orders` | customer | filters/page | own orders; 200 |
| GET | `/orders/{publicId}` | customer or verified guest token | opaque public ID | own order details; 200/404 |
| POST | `/orders/{publicId}/cancel` | customer/guest proof | reason | transition result; 200/409 | **required** |
| POST | `/orders/{publicId}/returns` | customer | order item IDs, reason, evidence metadata | return request; 201/409/422 | **required** |
| GET | `/orders/{publicId}/tracking` | owner/guest proof | no body | order and shipment timeline; 200/404 |
| POST | `/payments/{paymentId}/initiate` | owner | provider/method | provider action or pending result; 200/409 | **required** |
| GET | `/payments/{paymentId}` | owner | no body | verified server state; 200/404 |
| POST | `/payments/webhooks/{provider}` | provider signature | provider event payload | acknowledged; 200/400/401/409 | provider event ID |
| GET | `/shipping/zones` | optional | district/area | eligible zones; 200 |
| POST | `/shipping/quote` | optional | destination, cart reference | rate options; 200/422 |
| POST | `/reviews` | customer | order_item_id, rating, title/body | pending review; 201/409/422 | yes |
| GET | `/products/{slug}/reviews` | optional | rating/page | approved reviews; 200 |
| GET | `/me/notifications` | customer | unread/page | own notifications; 200 |
| PATCH | `/me/notifications/{id}/read` | customer | own ID | 200/404 | yes |
| GET/POST | `/support/tickets` | customer | list filters / subject, order, message | own tickets; 200/201/422 |
| GET/POST | `/support/tickets/{id}/messages` | ticket owner | message text | messages; 200/201/404 |

## Admin API inventory

All `/admin` endpoints require active staff identity, MFA policy, and named permission; mutations are audited and rate-limited.

| Method and path | Permission | Purpose / key request |
|---|---|---|
| GET `/admin/dashboard` | `dashboard.view` | date range; KPI and charts |
| CRUD `/admin/products`, `/admin/products/{id}` | `catalog.products.*` | validated catalog fields |
| POST `/admin/products/import`, GET `/admin/products/export` | `catalog.import|export` | async validated file/export |
| CRUD `/admin/categories[/{id}]` | `catalog.categories.*` | parent, slug, SEO |
| CRUD `/admin/brands[/{id}]` | `catalog.brands.*` | profile/media/SEO |
| GET `/admin/inventory` | `inventory.view` | warehouse/variant/low-stock filters |
| POST `/admin/inventory/adjustments` | `inventory.adjust` | variant, warehouse, delta, reason, idempotency |
| POST `/admin/inventory/transfers` | `inventory.transfer` | source/destination, lines, reason |
| GET `/admin/inventory/movements` | `inventory.audit` | filters/pagination |
| GET `/admin/orders` | `orders.view` | status/date/customer search |
| GET `/admin/orders/{id}` | `orders.view` | detail and timeline |
| POST `/admin/orders/{id}/transitions` | `orders.transition` | target state, reason, idempotency |
| POST `/admin/orders/{id}/notes` | `orders.note` | internal note |
| POST `/admin/orders/{id}/invoice` | `orders.invoice` | rendered invoice |
| GET `/admin/customers[/{id}]` | `customers.view` | search/profile/history |
| PATCH `/admin/customers/{id}` | `customers.manage` | allow-listed status/segment |
| GET `/admin/payments` | `payments.view` | provider/status/time filters |
| POST `/admin/payments/{id}/reconcile` | `payments.reconcile` | provider reference |
| POST `/admin/payments/{id}/refunds` | `payments.refund` | amount/reason/idempotency |
| CRUD `/admin/couriers[/{id}]` | `shipping.couriers.manage` | masked provider settings |
| POST `/admin/shipments/{id}/book` | `shipping.book` | courier/service/idempotency |
| GET `/admin/shipments` | `shipping.view` | state/tracking filters |
| CRUD `/admin/shipping/zones` and `/rates` | `shipping.rates.manage` | area rules and fees |
| CRUD `/admin/coupons`, `/promotions` | `marketing.*` | campaign eligibility/date/limits |
| CRUD `/admin/cms/pages|banners|menus|posts` | `cms.*` | content/locale/schedule |
| GET `/admin/reviews` and PATCH `/admin/reviews/{id}` | `reviews.moderate` | approve/reject/reason |
| GET `/admin/reports/{sales|orders|products|inventory|customers|payments|refunds|couriers}` | `reports.view` | filters/date |
| POST `/admin/reports/exports` | `reports.export` | async CSV/Excel export |
| CRUD `/admin/staff`, `/roles`, `/permissions` | `access.staff|roles.manage` | strict privilege checks |
| GET `/admin/audit-logs` | `audit.view` | actor/action/subject/date filters |
| CRUD `/admin/settings` | `settings.*` | masked secret configuration |
| GET `/admin/support/tickets`; POST `/admin/support/tickets/{id}/messages` | `support.*` | assignment/status/reply |

## Contract examples

### Checkout request

```http
POST /api/v1/checkout/orders
Authorization: Bearer <revocable-token>
Idempotency-Key: 01J...client-generated
Content-Type: application/json
```

```json
{
  "checkout_session_id": "01J...",
  "address_id": 42,
  "shipping_rate_id": 3,
  "coupon_code": "WELCOME10",
  "payment_method": "cod"
}
```

The backend fetches current variant prices and stock, revalidates the quote and eligibility, computes every amount, reserves stock, and creates the immutable order in one transaction. Client-provided totals are rejected/ignored.

### Success response

```json
{
  "success": true,
  "message": "Order created",
  "data": {
    "order": {
      "id": "01J...",
      "order_number": "AS-2026-...",
      "order_status": "pending",
      "payment_status": "pending",
      "fulfillment_status": "reserved",
      "total": "1280.00",
      "currency": "BDT"
    },
    "payment_action": {"type": "none"}
  },
  "meta": {"request_id": "uuid"}
}
```

### Error response

```json
{
  "success": false,
  "message": "Requested stock is no longer available",
  "errors": {"items": ["Refresh the cart and try again."]},
  "code": "STOCK_CONFLICT",
  "request_id": "uuid"
}
```

## Status codes and generated contract

Use 200/201/202/204 on success, 401 unauthenticated, 403 forbidden, 404 non-disclosing missing/unauthorized object, 409 state/idempotency/stock conflict, 422 validation, 429 throttled, 500 generic correlation-only error, 502/503 controlled provider unavailable. Generate and version OpenAPI 3.1 from Laravel routes/resources and validate it in CI. This draft must be reviewed before implementation; provider-specific webhook schemas are not invented here.
