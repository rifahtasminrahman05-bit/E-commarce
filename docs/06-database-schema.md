# Database Schema Specification

## Conventions

- MySQL 8/InnoDB; `BIGINT UNSIGNED` auto-increment primary keys unless a public opaque identifier is required.
- UTC `DATETIME(6)` timestamps. Laravel `created_at`, `updated_at`; add `deleted_at` only for recoverable catalog/identity records.
- Monetary values are `DECIMAL(12,2)` plus `CHAR(3) currency_code` where a financial transaction is recorded. Never use floating-point types for money.
- Statuses are constrained `VARCHAR` values validated by application state machines; avoid database ENUMs that make deployment changes brittle.
- `id` is PK unless noted. Foreign keys use `RESTRICT` for financial/audit history, `CASCADE` only for dependent nonfinancial children, and `SET NULL` for optional actor/provider references.
- Snapshot order lines, addresses, product names, SKUs, selected variants, prices, discounts, and shipping rates at purchase time.
- Every sellable product has at least one `product_variants` row. A simple/non-configurable product uses one default variant; cart, stock, reservation, movement, and order references target that sellable variant. `products.sku` is a distinct catalog/master SKU and must not be reused as a variant SKU.

## Identity and customer tables

| Table | Columns (type; `?` nullable; `=` default) | Keys and relationships |
|---|---|---|
| `users` | `id BIGINT`; `name VARCHAR(160)`; `email VARCHAR(190)?`; `phone_e164 VARCHAR(16)?`; `password VARCHAR(255)?`; `status VARCHAR(24)=active`; `email_verified_at DATETIME?`; `phone_verified_at DATETIME?`; `preferred_locale VARCHAR(5)=bn`; `last_login_at DATETIME?`; timestamps; `deleted_at DATETIME?` | PK; unique normalized email and phone (nullable policy reviewed); customer profiles and tokens cascade only where safe. |
| `roles` | `id`; `name VARCHAR(80)`; `slug VARCHAR(100)`; `description VARCHAR(255)?`; `is_system TINYINT=0`; timestamps | unique slug. |
| `permissions` | `id`; `name VARCHAR(120)`; `key VARCHAR(150)`; `module VARCHAR(60)`; timestamps | unique key. |
| `role_user` | `role_id BIGINT`; `user_id BIGINT`; `assigned_by BIGINT?`; `created_at` | PK `(role_id,user_id)`; FK roles/users; actor SET NULL. |
| `permission_role` | `permission_id BIGINT`; `role_id BIGINT`; `granted_by BIGINT?`; `created_at` | PK `(permission_id,role_id)`; FK permissions/roles. |
| `personal_access_tokens` | Laravel Sanctum token columns: `id`, `tokenable_type VARCHAR(190)`, `tokenable_id BIGINT`, `name VARCHAR(120)`, `token CHAR(64)`, `abilities TEXT?`, `last_used_at DATETIME?`, `expires_at DATETIME?`, timestamps | unique token; index morph owner; revoke on logout/device loss. |
| `customer_profiles` | `id`; `user_id BIGINT`; `first_name VARCHAR(80)?`; `last_name VARCHAR(80)?`; `gender VARCHAR(24)?`; `birth_date DATE?`; `marketing_consent_at DATETIME?`; `marketing_consent_source VARCHAR(60)?`; timestamps | unique user_id; cascade with account subject to retention policy. |
| `customer_addresses` | `id`; `user_id BIGINT`; `label VARCHAR(60)?`; `recipient_name VARCHAR(160)`; `phone_e164 VARCHAR(16)`; `division VARCHAR(100)`; `district VARCHAR(100)`; `area VARCHAR(120)`; `address_line VARCHAR(500)`; `postal_code VARCHAR(20)?`; `latitude DECIMAL(10,7)?`; `longitude DECIMAL(10,7)?`; `is_default TINYINT=0`; timestamps; `deleted_at?` | FK user; `(user_id,is_default)` index; checkout copies address snapshot. |
| `customer_devices` | `id`; `user_id BIGINT`; `platform VARCHAR(24)`; `push_token VARCHAR(512)`; `device_name VARCHAR(120)?`; `last_seen_at DATETIME?`; timestamps | unique push token; FK user cascade; never expose tokens publicly. |

## Catalog tables

| Table | Columns | Keys and relationships |
|---|---|---|
| `categories` | `id`; `parent_id BIGINT?`; `name VARCHAR(120)`; `slug VARCHAR(150)`; `description TEXT?`; `image_key VARCHAR(512)?`; `sort_order INT=0`; `is_active TINYINT=1`; `seo_title VARCHAR(180)?`; `seo_description VARCHAR(320)?`; timestamps; soft delete | self FK parent RESTRICT/SET NULL policy; unique slug. |
| `brands` | `id`; `name VARCHAR(120)`; `slug VARCHAR(150)`; `logo_key VARCHAR(512)?`; `description TEXT?`; `seo_title VARCHAR(180)?`; `seo_description VARCHAR(320)?`; `is_active TINYINT=1`; timestamps; soft delete | unique slug. |
| `products` | `id`; `category_id BIGINT`; `brand_id BIGINT?`; `name VARCHAR(180)`; `slug VARCHAR(200)`; `sku VARCHAR(80)`; `barcode VARCHAR(80)?`; `description LONGTEXT?`; `specifications JSON?`; `status VARCHAR(24)=draft`; `track_inventory TINYINT=1`; `price DECIMAL(12,2)`; `compare_at_price DECIMAL(12,2)?`; `cost DECIMAL(12,2)?`; `currency_code CHAR(3)=BDT`; `low_stock_threshold INT=5`; SEO title/description/canonical; timestamps; soft delete | unique slug and catalog/master SKU; category RESTRICT; brand SET NULL. |
| `product_variants` | `id`; `product_id BIGINT`; `sku VARCHAR(80)`; `barcode VARCHAR(80)?`; `name VARCHAR(140)`; `attributes JSON`; `price DECIMAL(12,2)?`; `compare_at_price DECIMAL(12,2)?`; `is_default TINYINT=0`; `is_active TINYINT=1`; timestamps | unique sellable SKU, distinct from master SKU; FK product cascade; index product/active; exactly one active default per product enforced transactionally in application and migration tests. |
| `product_images` | `id`; `product_id BIGINT`; `variant_id BIGINT?`; `storage_key VARCHAR(512)`; `alt_text VARCHAR(255)?`; `sort_order INT=0`; `is_primary TINYINT=0`; timestamps | product cascade, variant SET NULL. |
| `attributes` | `id`; `name VARCHAR(100)`; `slug VARCHAR(120)`; `data_type VARCHAR(24)`; `is_filterable TINYINT=0`; timestamps | unique slug. |
| `attribute_values` | `id`; `attribute_id BIGINT`; `value VARCHAR(160)`; `sort_order INT=0` | unique `(attribute_id,value)`; FK cascade. |
| `product_attribute_values` | `product_id BIGINT`; `attribute_value_id BIGINT` | composite PK; FKs; index attribute value. |

## Inventory, cart, and checkout tables

| Table | Columns | Keys and relationships |
|---|---|---|
| `warehouses` | `id`; `name VARCHAR(120)`; `code VARCHAR(40)`; `address JSON`; `is_active TINYINT=1`; timestamps | unique code. |
| `inventory_stocks` | `id`; `warehouse_id BIGINT`; `product_variant_id BIGINT`; `on_hand INT UNSIGNED=0`; `reserved INT UNSIGNED=0`; `updated_at` | unique `(warehouse_id,product_variant_id)`; check `reserved <= on_hand`; index variant/warehouse. |
| `stock_movements` | `id`; `warehouse_id BIGINT`; `product_variant_id BIGINT`; `movement_type VARCHAR(32)`; `quantity_delta INT`; `reference_type VARCHAR(80)?`; `reference_id BIGINT?`; `reason VARCHAR(255)`; `actor_user_id BIGINT?`; `idempotency_key VARCHAR(100)?`; `created_at` | append-only; unique nullable idempotency key scoped to operation; indexes variant/time and reference. |
| `stock_reservations` | `id`; `order_id BIGINT?`; `cart_id BIGINT?`; `product_variant_id BIGINT`; `warehouse_id BIGINT`; `quantity INT UNSIGNED`; `status VARCHAR(24)=active`; `expires_at DATETIME`; `released_at DATETIME?`; timestamps | indexes `(status,expires_at)`, `(order_id,status)`; FK restrictions preserve order audit. |
| `carts` | `id`; `user_id BIGINT?`; `guest_token_hash CHAR(64)?`; `status VARCHAR(24)=active`; `currency_code CHAR(3)=BDT`; `expires_at DATETIME?`; timestamps | unique user active cart policy enforced transactionally; unique guest token hash. |
| `cart_items` | `id`; `cart_id BIGINT`; `product_variant_id BIGINT`; `quantity INT UNSIGNED`; timestamps | unique `(cart_id,product_variant_id)`; cascade cart; quantity bounded in application. |
| `wishlists` | `id`; `user_id BIGINT`; `name VARCHAR(100)?`; timestamps | FK user; unique primary wishlist per customer policy. |
| `wishlist_items` | `wishlist_id BIGINT`; `product_id BIGINT`; `created_at` | composite PK; cascade wishlist; product RESTRICT. |
| `checkout_sessions` | `id`; `public_id CHAR(26)`; `cart_id BIGINT`; `user_id BIGINT?`; `idempotency_key VARCHAR(100)`; `shipping_zone_id BIGINT?`; `shipping_rate_id BIGINT?`; `quote JSON`; `status VARCHAR(24)`; `expires_at DATETIME`; timestamps | unique public ID and `(cart_id,idempotency_key)`; expire stale quotes. |
| `shipping_zones` | `id`; `name VARCHAR(100)`; `code VARCHAR(40)`; `area_rules JSON`; `is_active TINYINT=1`; timestamps | unique code. |
| `shipping_rates` | `id`; `shipping_zone_id BIGINT`; `name VARCHAR(100)`; `base_fee DECIMAL(12,2)`; `free_threshold DECIMAL(12,2)?`; `cod_fee DECIMAL(12,2)=0`; `estimated_min_days TINYINT?`; `estimated_max_days TINYINT?`; `is_active TINYINT=1`; `starts_at/ends_at DATETIME?`; timestamps | FK zone; index zone/active/date. |

## Orders, payments, and shipping tables

| Table | Columns | Keys and relationships |
|---|---|---|
| `orders` | `id`; `order_number VARCHAR(32)`; `public_id CHAR(26)`; `user_id BIGINT?`; `checkout_session_id BIGINT?`; `order_status VARCHAR(32)`; `payment_status VARCHAR(32)`; `fulfillment_status VARCHAR(32)`; `currency_code CHAR(3)`; subtotal/discount/shipping/tax/total `DECIMAL(12,2)`; `coupon_code VARCHAR(40)?`; `idempotency_key VARCHAR(100)`; `placed_at DATETIME`; timestamps | unique order number/public ID; unique checkout idempotency scope; user SET NULL; indexes status/time, user/time. |
| `order_items` | `id`; `order_id BIGINT`; `product_id BIGINT?`; `product_variant_id BIGINT?`; `sku_snapshot VARCHAR(80)`; `product_name_snapshot VARCHAR(180)`; `variant_snapshot JSON?`; `quantity INT UNSIGNED`; `unit_price DECIMAL(12,2)`; `discount_amount DECIMAL(12,2)=0`; `tax_amount DECIMAL(12,2)=0`; `line_total DECIMAL(12,2)` | order cascade; catalog FKs SET NULL; immutable snapshots; FK/index order. |
| `order_addresses` | `id`; `order_id BIGINT`; `address_type VARCHAR(16)`; recipient/phone/address/area/postal snapshot fields | unique `(order_id,address_type)`; order cascade. |
| `order_status_histories` | `id`; `order_id BIGINT`; `from_status VARCHAR(32)?`; `to_status VARCHAR(32)`; `note VARCHAR(500)?`; `actor_user_id BIGINT?`; `created_at` | append-only; index order/time; actor SET NULL. |
| `payments` | `id`; `order_id BIGINT`; `provider VARCHAR(32)`; `method VARCHAR(32)`; `status VARCHAR(32)`; `amount DECIMAL(12,2)`; `currency_code CHAR(3)`; `provider_payment_id VARCHAR(120)?`; `idempotency_key VARCHAR(100)`; `verified_at DATETIME?`; `failure_code VARCHAR(80)?`; timestamps | unique `(provider,provider_payment_id)` nullable; unique idempotency scope; FK order RESTRICT. |
| `payment_transactions` | `id`; `payment_id BIGINT`; `provider_reference VARCHAR(150)?`; `transaction_type VARCHAR(32)`; `status VARCHAR(32)`; `amount DECIMAL(12,2)`; `currency_code CHAR(3)`; `provider_payload_redacted JSON?`; `occurred_at DATETIME?`; timestamps | unique provider reference where present; append-only; FK payment RESTRICT. |
| `refunds` | `id`; `payment_id BIGINT`; `order_id BIGINT`; `amount DECIMAL(12,2)`; `currency_code CHAR(3)`; `status VARCHAR(24)`; `reason VARCHAR(255)`; `provider_refund_id VARCHAR(120)?`; `idempotency_key VARCHAR(100)`; `requested_by BIGINT?`; `processed_at DATETIME?`; timestamps | unique idempotency and provider refund ref; FK RESTRICT; total successful refunds <= captured payment in transaction. |
| `payment_webhook_events` | `id`; `provider VARCHAR(32)`; `provider_event_id VARCHAR(150)`; `event_type VARCHAR(100)`; `signature_valid TINYINT`; `payload_hash CHAR(64)`; `processing_status VARCHAR(24)`; `received_at DATETIME`; `processed_at DATETIME?` | unique `(provider,provider_event_id)`; payload secrets/PII redacted or encrypted. |
| `couriers` | `id`; `name VARCHAR(100)`; `code VARCHAR(40)`; `config_encrypted JSON?`; `is_active TINYINT=0`; timestamps | unique code; credentials encrypted and never returned. |
| `courier_shipments` | `id`; `order_id BIGINT`; `courier_id BIGINT?`; `consignment_id VARCHAR(120)?`; `status VARCHAR(32)`; `tracking_url VARCHAR(500)?`; `delivery_fee DECIMAL(12,2)`; `cod_amount DECIMAL(12,2)=0`; `idempotency_key VARCHAR(100)`; `booked_at DATETIME?`; timestamps | unique provider consignment where present; unique idempotency; FK order RESTRICT. |
| `shipment_events` | `id`; `courier_shipment_id BIGINT`; `provider_event_id VARCHAR(150)?`; `status VARCHAR(32)`; `description VARCHAR(500)?`; `occurred_at DATETIME?`; `payload_redacted JSON?`; `created_at` | unique nullable provider event per shipment; append-only; index shipment/time. |

## Promotion, review, CMS, support, notification, administration

| Table | Columns | Keys and relationships |
|---|---|---|
| `coupons` | `id`; `code VARCHAR(40)`; `discount_type VARCHAR(16)`; `discount_value DECIMAL(12,2)`; `minimum_order DECIMAL(12,2)=0`; `maximum_discount DECIMAL(12,2)?`; `usage_limit INT?`; `usage_count INT=0`; `starts_at/ends_at DATETIME?`; `is_active TINYINT=1`; timestamps | unique normalized code; check positive value and valid ranges. |
| `coupon_usages` | `id`; `coupon_id BIGINT`; `order_id BIGINT`; `user_id BIGINT?`; `discount_amount DECIMAL(12,2)`; `status VARCHAR(20)`; `created_at` | unique `(coupon_id,order_id)`; FKs RESTRICT for financial history. |
| `promotions` | `id`; `name VARCHAR(150)`; `type VARCHAR(32)`; `rules JSON`; `starts_at/ends_at DATETIME`; `is_active TINYINT`; timestamps | index active/date. |
| `promotion_products` | `promotion_id BIGINT`; `product_id BIGINT`; `created_at` | composite PK; FKs. |
| `product_reviews` | `id`; `product_id BIGINT`; `user_id BIGINT`; `order_item_id BIGINT`; `rating TINYINT`; `title VARCHAR(160)?`; `body TEXT`; `status VARCHAR(20)=pending`; `moderated_by BIGINT?`; `moderated_at DATETIME?`; timestamps | unique order item/customer; check rating 1..5; index product/status/time. |
| `pages` | `id`; `slug VARCHAR(180)`; `locale VARCHAR(5)`; `title VARCHAR(180)`; `body LONGTEXT`; SEO fields; `status VARCHAR(20)`; `published_at DATETIME?`; timestamps | unique `(locale,slug)`. |
| `banners` | `id`; `title VARCHAR(160)`; `image_key VARCHAR(512)`; `target_url VARCHAR(500)?`; `locale VARCHAR(5)`; `position VARCHAR(60)`; `starts_at/ends_at DATETIME?`; `is_active TINYINT`; timestamps | index position/active/date. |
| `menus` | `id`; `name VARCHAR(80)`; `location VARCHAR(60)`; `locale VARCHAR(5)`; timestamps | unique `(location,locale)`. |
| `menu_items` | `id`; `menu_id BIGINT`; `parent_id BIGINT?`; `label VARCHAR(120)`; `url VARCHAR(500)`; `sort_order INT`; `is_active TINYINT` | menu cascade; self parent. |
| `blog_posts` | `id`; `author_id BIGINT?`; `slug VARCHAR(180)`; `locale VARCHAR(5)`; `title VARCHAR(180)`; `body LONGTEXT`; SEO fields; `status VARCHAR(20)`; `published_at DATETIME?`; timestamps | unique `(locale,slug)`; author SET NULL. |
| `support_tickets` | `id`; `ticket_number VARCHAR(32)`; `user_id BIGINT?`; `order_id BIGINT?`; `subject VARCHAR(180)`; `status VARCHAR(24)`; `priority VARCHAR(20)`; `assigned_to BIGINT?`; timestamps | unique ticket; indexed status/assignee/time. |
| `support_messages` | `id`; `ticket_id BIGINT`; `sender_user_id BIGINT?`; `body TEXT`; `is_internal TINYINT=0`; `created_at` | ticket cascade; index ticket/time. |
| `notification_templates` | `id`; `key VARCHAR(100)`; `locale VARCHAR(5)`; `channel VARCHAR(20)`; `subject VARCHAR(180)?`; `body LONGTEXT`; `is_active TINYINT`; timestamps | unique `(key,locale,channel)`. |
| `notifications` | `id`; `user_id BIGINT`; `type VARCHAR(100)`; `data JSON`; `read_at DATETIME?`; timestamps | user cascade; index user/read/time. |
| `notification_logs` | `id`; `user_id BIGINT?`; `template_id BIGINT?`; `channel VARCHAR(20)`; `provider VARCHAR(40)`; `destination_hash CHAR(64)?`; `status VARCHAR(24)`; `attempts TINYINT`; `provider_reference VARCHAR(150)?`; `failure_code VARCHAR(80)?`; `sent_at DATETIME?`; timestamps | indexes status/time; avoid logging raw OTP/body/secret. |
| `audit_logs` | `id`; `actor_user_id BIGINT?`; `action VARCHAR(100)`; `subject_type VARCHAR(100)`; `subject_id BIGINT?`; `request_id CHAR(36)?`; `ip_hash CHAR(64)?`; `before_redacted JSON?`; `after_redacted JSON?`; `created_at` | append-only; indexes actor/time and subject/time. |
| `activity_logs` | `id`; `actor_user_id BIGINT?`; `event VARCHAR(120)`; `context_redacted JSON?`; `created_at` | append-only, retention policy; index actor/time. |
| `settings` | `id`; `key VARCHAR(120)`; `value JSON`; `is_secret TINYINT=0`; `updated_by BIGINT?`; timestamps | unique key; encrypt secret values and return masked values. |

## Integrity and lifecycle rules

Order/payment/fulfillment status are separate. Never hard-delete completed orders, payments, refunds, stock movements, webhook events, or audits. Reserve stock with an atomic conditional update inside the order transaction; create reservation and movement records in the same transaction. Unique provider/event and idempotency keys protect retries. See [07-database-erd.md](./07-database-erd.md).
