# All Shop

বাংলা-ভাষার, মোবাইল-উপযোগী PHP storefront—SQLite database, অর্ডার ব্যবস্থাপনা এবং bKash Tokenized Checkout integration-সহ।

## বর্তমান অ্যাপ ও target architecture

এই README-র মূল সেটআপ ও feature বর্ণনা **বর্তমান PHP/SQLite অ্যাপের** জন্য। আলাদা [architecture package](./docs/)-এ Laravel API, Next.js storefront/admin, MySQL ও Redis-ভিত্তিক নতুন সংস্করণের পরিকল্পনা আছে। Owner rewrite অনুমোদন করেছেন এবং M1 platform foundation শুরু হয়েছে; তবে target stack এখনো customer checkout, staff login, catalog বা payment/courier workflows বাস্তবায়ন করেনি। বর্তমান অ্যাপ ও SQLite database অক্ষত থাকবে; migration ও cutover আলাদা approval ছাড়া হবে না।

### Architecture package index

| ডকুমেন্ট | বিষয় |
|---|---|
| [01 — Project overview](./docs/01-project-overview.md) | উদ্দেশ্য, বর্তমান baseline, feasibility ও approval boundary |
| [02 — SRS](./docs/02-srs.md) | scope, functional/non-functional requirements |
| [03 — Functional requirements](./docs/03-functional-requirements.md) | customer/admin journeys ও acceptance criteria |
| [04 — Non-functional requirements](./docs/04-non-functional-requirements.md) | performance, recovery, security ও accessibility targets |
| [05 — System architecture](./docs/05-system-architecture.md) | modular monolith, modules, integrations ও deployment topology |
| [06 — Database schema](./docs/06-database-schema.md) | proposed MySQL tables, keys, indexes ও relationships |
| [07 — Database ERD](./docs/07-database-erd.md) | entity diagram, integrity ও transaction strategy |
| [08 — API specification](./docs/08-api-specification.md) | versioned REST API inventory ও contracts |
| [09 — Authorization matrix](./docs/09-api-authorization-matrix.md) | roles, permissions ও policy requirements |
| [10 — Payment integration](./docs/10-payment-integration.md) | payment adapters, verification ও reconciliation |
| [11 — Courier integration](./docs/11-courier-integration.md) | courier adapters, booking ও COD reconciliation |
| [12 — Security architecture](./docs/12-security-architecture.md) | trust boundaries, threats ও controls |
| [13 — Security test report](./docs/13-security-test-report.md) | planned security coverage; assessment এখনো হয়নি |
| [14 — Deployment guide](./docs/14-deployment-guide.md) | target environments, releases ও migration safeguards |
| [15 — Backup and recovery](./docs/15-backup-recovery.md) | proposed backup, restore ও recovery targets |
| [16 — Testing strategy](./docs/16-testing-strategy.md) | test layers, commerce scenarios ও quality gates |
| [17 — Development roadmap](./docs/17-development-roadmap.md) | approval-gated milestones |
| [18 — Known limitations](./docs/18-known-limitations.md) | assumptions, unverified items ও owner decisions |
| [19 — Implementation status](./docs/19-implementation-status.md) | M1 progress, verified checks ও remaining gates |
| [20 — Production checklist](./docs/20-production-readiness-checklist.md) | launch readiness gates |
| [21 — Release preparation runbook](./docs/21-release-preparation.md) | PHP staging gates, platform boundaries, validation and explicit production blockers |

**বর্তমান অবস্থা:** architecture owner-approved; M1 foundation চলছে। Delivery policy এবং provider credentials/docs এখনো open, আর target architecture-এর provider integration, Docker runtime, security assessment বা backup restore সম্পন্ন হয়েছে—এমন দাবি করা হচ্ছে না। [Open decisions](./docs/18-known-limitations.md) এবং [implementation status](./docs/19-implementation-status.md) দেখুন।

### Workspace boundaries

| Path | কী আছে | Release status |
|---|---|---|
| Project root (`index.php`, `checkout.php`, `admin.php`, etc.) | বর্তমান server-rendered PHP/SQLite store; একমাত্র সম্পূর্ণ buyer checkout ও admin workflow | **Production release blocked**; staging hardening, operational setup ও business/provider approval বাকি |
| `backend/` | Laravel 13 API foundation | M1; পূর্ণ catalog, customer/staff auth, checkout, payment ও courier workflows নেই |
| `frontend/apps/storefront/` | Next.js storefront foundation | M1; current PHP store-এর বিকল্প checkout নয় |
| `frontend/apps/admin/` | Next.js admin preview | M1; production authentication/authorization নেই, public host-এ deploy করা যাবে না |
| `docs/` | Target architecture, limitations, checklists ও release runbook | Implementation নয়; প্রতিটি item-এর evidence আলাদাভাবে যাচাই করতে হবে |

এই separation ইচ্ছাকৃত: root PHP routes-কে workspace-এ সরালে বিদ্যমান URL, sessions ও SQLite data-তে migration ঝুঁকি তৈরি হয়। কোনো production cutover-এর আগে আলাদা migration/cutover approval প্রয়োজন।

## প্রস্তাবিত Laravel/Next.js platform — M1 foundation

এটি বর্তমান PHP storefront-এর বিকল্প production store নয়; customer checkout, staff login, catalog বা payment/courier workflows এখনো চালু নেই। নতুন API ও frontend আলাদা workspace-এ রাখা হয়েছে। Admin preview-তে authentication নেই—এটি public deployment-এ ব্যবহার করবেন না।

Container-based local preview-এর জন্য Docker ও Docker Compose v2 প্রয়োজন। Container ছাড়া backend/frontend আলাদাভাবে চালাতে PHP 8.4+, Composer 2 এবং Node.js 22+ প্রয়োজন:

1. `.env.compose.example` কপি করে `.env.compose` বানিয়ে `MYSQL_PASSWORD` ও `MYSQL_ROOT_PASSWORD`-এ আলাদা local-only password দিন।
2. `php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'` চালিয়ে তৈরি key-টি `.env.compose`-এর `BACKEND_APP_KEY`-এ বসান।
3. প্রজেক্ট root-এ ফিরে platform চালান:

   ```sh
   docker compose --env-file .env.compose up --build
   ```

4. Preview storefront `http://localhost:3000`, admin foundation `http://localhost:3001`, API health `http://localhost:8000/api/v1/health`-এ পাওয়া যাবে। Compose database-এর host port `3307` এবং Redis `6380`।

Compose stack development-only; production deployment নয়। Docker unavailable হলে এই Compose setup চালানো যাবে না; services আলাদাভাবে configure করে চালাতে হবে। Current PHP app-টি একই সময়ে চালাতে চাইলে `php -S localhost:8001 router.php` ব্যবহার করুন, কারণ নতুন API `8000` port ব্যবহার করে। Setup ও provider choices-এর অগ্রগতি [implementation status](./docs/19-implementation-status.md)-এ রাখুন।

## প্রয়োজন

- PHP 8.1+
- PDO SQLite ও cURL extensions
- bKash merchant credentials (শুধু bKash পেমেন্ট চালাতে)

## লোকাল সেটআপ

1. `.env.example` কপি করে `.env` বানান। একটি ব্যক্তিগত admin password দিন। উদাহরণ ফাইলের placeholder production-এ ব্যবহার করবেন না।
2. টার্মিনালে প্রজেক্ট ফোল্ডার থেকে চালান:

   ```sh
   php -S localhost:8000 router.php
   ```

3. `http://localhost:8000` খুলুন। SQLite database প্রথমবার স্বয়ংক্রিয়ভাবে `storage/allshop.sqlite`-এ তৈরি হবে।
4. অ্যাডমিন প্যানেল: `http://localhost:8000/admin.php`

`.env`-এর `ALLSHOP_ADMIN_USERNAME` ও `ALLSHOP_ADMIN_PASSWORD` প্রথম database initialization-এ admin account তৈরি করে। পাসওয়ার্ড database-এ hash আকারে থাকে। `.env`-কে public repository-তে commit করবেন না।

## bKash চালু করা

`.env`-এ bKash থেকে পাওয়া merchant credentials বসান:

```dotenv
ALLSHOP_APP_URL=https://your-domain.example
ALLSHOP_BKASH_ENV=sandbox
ALLSHOP_BKASH_APP_KEY=your-app-key
ALLSHOP_BKASH_APP_SECRET=your-app-secret
ALLSHOP_BKASH_USERNAME=your-merchant-username
ALLSHOP_BKASH_PASSWORD=your-merchant-password
```

প্রথমে `sandbox`-এ পরীক্ষা করুন। Live checkout-এর জন্য `ALLSHOP_BKASH_ENV=live`, HTTPS domain এবং bKash-এর live credentials ব্যবহার করুন। Callback-এর জন্য `ALLSHOP_APP_URL` অবশ্যই সঠিক public storefront URL হতে হবে। কোনো credential PHP/HTML source বা browser-এ প্রকাশ করবেন না।

Integrationটি bKash-এর [Grant Token](https://developer.bka.sh/docs/grant-token.md), [Create Payment](https://developer.bka.sh/docs/create-payment-3.md), [Execute Payment](https://developer.bka.sh/docs/execute-payment-4.md) এবং [Query Payment](https://developer.bka.sh/docs/query-payment.md) API ব্যবহার করে। `paid` হিসেবে সংরক্ষণের আগে server-side-এ payment ID, invoice, amount, currency এবং `Completed` status মেলানো হয়। Credentials না থাকলে bKash checkout বন্ধ থাকবে; ক্যাশ অন ডেলিভারি ব্যবহার করা যাবে।

## কী কী আছে

- ক্যাটাগরি/সার্চ, বাংলা-English-Banglish search aliases ও catalog suggestions, পণ্যের আলাদা বিবরণ পেজ এবং responsive storefront
- Session-based shopping cart; পরিমাণ বদলানো/পণ্য সরানো
- SQLite-এ স্থায়ী order ও order-item snapshot; checkout form validation
- ক্যাশ অন ডেলিভারি এবং bKash Tokenized Checkout
- অর্ডার নম্বর ও ফোন নম্বর দিয়ে order tracking
- লগইন-সুরক্ষিত admin dashboard: পণ্য যোগ/সম্পাদনা/স্টোর থেকে সরানো, order status ও COD payment আপডেট, newsletter subscriber দেখা
- Session-based wishlist, শতাংশ/নির্দিষ্ট-টাকার promo code, সীমিত ব্যবহার/মেয়াদসহ coupon management এবং চলতি কুপনের public offers page
- কেনা পণ্যের verified review submission ও admin moderation; শুধু অনুমোদিত review-ই storefront-এ দেখায়
- বাংলা সাহায্য/FAQ, যোগাযোগ form এবং admin inbox-এ customer inquiry triage
- Catalog-এ price range, in-stock filter, sort by newest/price/rating, session-based product comparison (সর্বোচ্চ ৩টি) এবং cart free-delivery progress
- Seller application, admin verification/approval, আলাদা seller login এবং seller-owned catalog draft/review; approved seller submission-ও এখনো buyer storefront বা checkout-এ যায় না
- CSRF protection, output escaping, prepared SQL statements, hardened session cookie/security headers, admin login lockout/session expiry এবং payment callback যাচাই

ডেলিভারি চার্জ ৳৮০; ৳১,৫০০ বা তার বেশি অর্ডারে ফ্রি। ছবি ও Google Fonts-এর জন্য storefront ইন্টারনেট ব্যবহার করে।

## ই-কমার্স ফিচার রোডম্যাপ

| ধাপ | ফিচার | অবস্থা |
|---|---|---|
| ১. Storefront ও checkout | পণ্য ক্যাটালগ, সার্চ/ক্যাটাগরি, কার্ট, COD/bKash, স্থায়ী অর্ডার, অর্ডার ট্র্যাক | যুক্ত |
| ২. কেনাকাটার অভিজ্ঞতা | Wishlist, coupon/discount ও public offers page, verified product review ও moderation, help/FAQ ও customer support inbox, catalog filters/sorting, product comparison ও free-delivery progress | যুক্ত |
| ৩. স্মার্ট ডিসকভারি | বাংলা/English/Banglish search, curated product aliases, catalog suggestions, relevance order ও privacy-minimized search analytics | search ও ৭ দিনের aggregate analytics যুক্ত; typo-tolerant search পরবর্তী |
| ৪. Inventory ও fulfilment | Stock quantity, low-stock alert, size/color variants, shipping fee/zone এবং courier tracking | Stock ও variants যুক্ত; shipping পরবর্তী |
| ৫. Marketplace seller foundation | Seller application, manual verification, seller account, owned catalog draft ও admin review | Stage 1 যুক্ত; seller checkout/order split/settlement এখনো বন্ধ |
| ৬. Customer accounts | Registration/login, order history, saved addresses, wishlist sync | পরবর্তী |
| ৭. Store operations | Revenue/sales reports, CSV export, automated email/SMS, refund/return workflow | পরবর্তী |

### Seller marketplace: Stage 1 সীমা

`seller_apply.php` থেকে ব্যবসায়ীরা আবেদন করতে এবং অনুমোদিত সেলাররা `seller_login.php` / `seller.php` দিয়ে নিজের catalog draft পরিচালনা করতে পারেন। Admin panel-এর “সেলার” বিভাগে ব্যবসা/পরিচয়, ফোন, payout-account ownership এবং প্রয়োজনীয় legal/tax তথ্য হাতে যাচাই করে আবেদন অনুমোদন বা প্রত্যাখ্যান করা যায়। এই পর্যায়ে পরিচয়পত্র, payout credential বা legal/tax document আপলোড/সংরক্ষণ হয় না; প্রশাসককে উপযুক্ত বাহ্যিক প্রক্রিয়ায় তথ্য যাচাই করতে হবে। Seller account এবং catalog record-এ owner check আছে; admin-এর catalog approval কেবল review approval—পণ্য storefront-এ প্রকাশিত বা বিক্রিযোগ্য নয়।

Buyer cart, checkout, COD/bKash, orders এবং বর্তমান single-vendor product catalog এই ধাপে বদলানো হয়নি। Split orders, seller checkout, commission calculation/deduction, payout ledger, refunds/chargebacks, courier/payment integrations এবং production release আলাদা implementation ও approval-এর অপেক্ষায়। অনুমোদিত policy অনুযায়ী ভবিষ্যৎ commission category-ভিত্তিক ১০%, discount-পরবর্তী item subtotal-এ, shipping ও tax বাদ দিয়ে হিসাব হবে; delivered order-এর পর fortnightly manual payout এবং refund/chargeback-এ seller responsibility ও commission reversal—এগুলো এখনো চালু নয়।

প্রথম ধাপে wishlist গেস্ট session-এ থাকে। `ALLSHOP10` ডিফল্ট কুপনটি ৳১,০০০ বা বেশি পণ্যমূল্যে ১০% ছাড় দেয় (সর্বোচ্চ ৳৫০০)। অ্যাডমিন panel থেকে সময়সীমা ও ব্যবহারসীমাসহ নতুন promo code তৈরি/বন্ধ করা যায়। রিভিউ দিতে অর্ডার নম্বর, একই অর্ডারের ফোন এবং delivered/paid অর্ডারে থাকা পণ্য মেলাতে হয়; প্রকাশের আগে অ্যাডমিন অনুমোদন প্রয়োজন।

সার্চে বাংলা, English এবং কিছু প্রচলিত বাংলা/English synonym অন্তর্ভুক্ত। অ্যাডমিন পণ্য সম্পাদনায় “সার্চ শব্দ” ঘরে সেই পণ্যের Banglish, বিকল্প বানান ও synonym কমা দিয়ে যোগ করুন। Search analytics-এ শুধু normalized query ও result count hourly aggregate হিসেবে সর্বোচ্চ ৭ দিন থাকে; IP, account বা session identifier রাখা হয় না। স্পষ্ট email/দীর্ঘ phone-number-এর মতো query aggregate-এ রাখা হয় না। Admin panel-এর “সার্চ ডেটা” পেজে popular/no-result terms দেখা যায়। Click-to-purchase attribution এবং general typo tolerance এখনো চালু নয়।

পণ্য তুলনা করতে ক্যাটালগ বা পণ্যের বিবরণ থেকে “তুলনা” বেছে নিন; একই session-এ সর্বোচ্চ তিনটি পণ্য যোগ করা যায়। তুলনা পাতায় দাম, রেটিং, ক্যাটাগরি, স্টক ও বিবরণ পাশাপাশি দেখুন। এটি account ছাড়াই কাজ করে এবং তুলনার তালিকা শুধু browser session-এ থাকে।

যোগাযোগ ফর্মের বার্তাগুলো database-এ সংরক্ষিত হয় এবং admin panel-এর “বার্তা” বিভাগ থেকে নতুন/চলমান/সম্পন্ন হিসেবে পরিচালনা করা যায়। এই সংস্করণে ইমেইল স্বয়ংক্রিয়ভাবে পাঠানো হয় না; অ্যাডমিন inbox থেকে ক্রেতাকে উত্তর দেবেন।

পণ্য সম্পাদনার সময় inventory tracking চালু করে সাধারণ stock ও low-stock সীমা দিন, অথবা প্রতি লাইনে `ভ্যারিয়েন্টের নাম | স্টক` লিখে size/color option যোগ করুন। নতুন sample পণ্যে ২০টি stock থাকে; আগের database-এর পণ্যগুলোতে কৃত্রিম stock বসানো হয় না—সেগুলোর inventory admin থেকে চালু করতে হবে। Checkout-এ stock transaction-এর মধ্যে reserve হয়; shipment-এর আগে unpaid/COD অর্ডার বাতিল বা bKash payment ব্যর্থ/বাতিল হলে তা ফেরত আসে। বাতিল অর্ডার পুনরায় চালু করা যায় না; shipped/delivered বা paid অর্ডারে cancellation-এর বদলে return/refund workflow দরকার, যা পরবর্তী ধাপ। bKash-এর payment status অনিশ্চিত থাকলে stock স্বয়ংক্রিয়ভাবে ফেরে না, যাতে সফল payment-এর পর অতিরিক্ত বিক্রি না হয়।

## Production-এ প্রকাশের আগে

- আগে [.env.example](./.env.example) থেকে production `.env` তৈরি করবেন না; secret manager/host environment ব্যবহার করুন অথবা `.env` ফাইলের permission `600` করুন। `ALLSHOP_ENV=production`, public `https://` `ALLSHOP_APP_URL`, অন্তত ৩২ অক্ষরের random `ALLSHOP_APP_KEY`, অন্তত ২০ অক্ষরের unique admin password এবং `ALLSHOP_SECURE_COOKIES=true` আবশ্যক। App key তৈরির উদাহরণ: `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`
- `ALLSHOP_DB_PATH`-এ public project/web root-এর বাইরের pre-created absolute path দিন। PHP user-কে শুধু database directory-তে প্রয়োজনীয় write permission দিন; database, WAL ও SHM ফাইল backup scope-এ রাখুন।
- Apache-তে root [`.htaccess`](./.htaccess) এবং [`storage/.htaccess`](./storage/.htaccess) সক্রিয় আছে নিশ্চিত করুন। Nginx বা অন্য server হলে `.env`, source/config/docs, SQLite/WAL/SHM এবং `storage/`-এ direct HTTP access deny করুন। PHP document root project root না করে আলাদা public directory ব্যবহার করাই উত্তম; অন্য server config অবশ্যই staging-এ যাচাই করতে হবে।
- TLS, HTTP→HTTPS redirect, HSTS, `display_errors=Off`, `log_errors=On`, supported PHP 8.1+ ও PDO SQLite/cURL/mbstring/intl চালু রাখুন। TLS reverse proxy ব্যবহার করলে `ALLSHOP_SECURE_COOKIES=true` দিন এবং proxy-তেই HSTS/HTTPS redirect enforce করুন; forwarded client headers-কে app-এ অন্ধভাবে trust করবেন না।
- প্রথম চালু করার আগে staging-এ login, checkout, stock, coupon, order tracking এবং backup restore পরীক্ষা করুন। Admin-এ পাঁচটি ভুল password attempt-এর পর ১৫ মিনিট lockout হয়; admin session ৩০ মিনিট নিষ্ক্রিয় থাকলে মেয়াদ শেষ হয়। নিয়মিত encrypted backup ও restore drill ছাড়া production data রাখবেন না।
- Production-এ sandbox bKash checkout দেখানো হয় না; complete live HTTPS configuration ছাড়া checkout-এ bKash অনুপলভ্য থাকবে। bKash live mode কেবল merchant credentials, official configuration এবং sandbox-এ সফল/ব্যর্থ/বাতিল callback flow যাচাইয়ের পর চালু করুন। Courier integration, automated email, customer accounts, returns/refunds, tax/privacy policy ও delivery-zone pricing এই PHP release-এ সম্পন্ন নয়—owner approval/credentials ছাড়া এগুলোকে চালু বা উপলভ্য বলে দেখাবেন না।
- Production checklist ও launch blockers: [20-production-readiness-checklist.md](./docs/20-production-readiness-checklist.md) এবং [18-known-limitations.md](./docs/18-known-limitations.md)।
