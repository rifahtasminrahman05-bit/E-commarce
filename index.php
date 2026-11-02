<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

$category = trim((string) ($_GET['category'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$normalizedSearch = normalize_search_text($search);
$searchTokenCount = $normalizedSearch === '' ? 0 : count(explode(' ', $normalizedSearch));
$searchError = '';
if (text_length($search) > 80) {
    $searchError = 'সার্চ শব্দ ৮০ অক্ষরের মধ্যে লিখুন।';
} elseif ($search !== '' && $normalizedSearch === '') {
    $searchError = 'পণ্য খুঁজতে বাংলা, English অক্ষর বা সংখ্যা লিখুন।';
} elseif ($searchTokenCount > 8) {
    $searchError = 'একবারে সর্বোচ্চ ৮টি শব্দ দিয়ে খুঁজুন।';
}
$sort = (string) ($_GET['sort'] ?? 'featured');
$sortOptions = ['featured', 'price_low', 'price_high', 'rating', 'newest'];
$minimumPriceInput = trim((string) ($_GET['min_price'] ?? ''));
$maximumPriceInput = trim((string) ($_GET['max_price'] ?? ''));
$inStockOnly = ($_GET['in_stock'] ?? '') === '1';
$filterError = '';
$newsletterMessage = flash('newsletter');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'newsletter') {
    if (!verify_csrf()) {
        flash('newsletter', 'অনুরোধটি যাচাই করা যায়নি। আবার চেষ্টা করুন।');
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            flash('newsletter', 'সঠিক ইমেইল ঠিকানা লিখুন।');
        } else {
            $statement = db()->prepare('INSERT OR IGNORE INTO newsletter_subscribers (email) VALUES (:email)');
            $statement->execute(['email' => $email]);
            flash('newsletter', $statement->rowCount() > 0 ? 'ধন্যবাদ! আপনি আমাদের তালিকায় যুক্ত হয়েছেন।' : 'এই ইমেইলটি ইতিমধ্যে তালিকায় আছে।');
        }
    }
    redirect('index.php#newsletter');
}

$allProducts = products();
$categories = array_values(array_unique(array_column($allProducts, 'category')));
$filtered = $searchError === '' ? products($category, $search) : [];
$searchSuggestions = product_search_suggestions();
$ratings = product_ratings(array_keys($filtered));
$minimumPrice = $minimumPriceInput === '' ? null : filter_var($minimumPriceInput, FILTER_VALIDATE_INT);
$maximumPrice = $maximumPriceInput === '' ? null : filter_var($maximumPriceInput, FILTER_VALIDATE_INT);
$validMinimumPrice = $minimumPriceInput === '' || ($minimumPrice !== false && $minimumPrice >= 0 && $minimumPrice <= 100000000);
$validMaximumPrice = $maximumPriceInput === '' || ($maximumPrice !== false && $maximumPrice >= 0 && $maximumPrice <= 100000000);
if (!$validMinimumPrice || !$validMaximumPrice) {
    $filterError = 'দামের সীমা ০ থেকে ১০ কোটি টাকার মধ্যে পূর্ণ সংখ্যায় লিখুন।';
} elseif ($minimumPrice !== null && $maximumPrice !== null && $minimumPrice > $maximumPrice) {
    $filterError = 'সর্বনিম্ন দাম সর্বোচ্চ দামের চেয়ে বেশি হতে পারবে না।';
}
if (!in_array($sort, $sortOptions, true)) {
    $sort = 'featured';
}
if ($filterError === '') {
    $filtered = array_filter($filtered, static function (array $product) use ($minimumPrice, $maximumPrice, $inStockOnly): bool {
        if ($minimumPrice !== null && $product['price'] < $minimumPrice) {
            return false;
        }
        if ($maximumPrice !== null && $product['price'] > $maximumPrice) {
            return false;
        }
        $availableStock = product_available_stock($product);
        return !$inStockOnly || $availableStock === null || $availableStock > 0;
    });
}
if ($sort === 'newest') {
    $filtered = array_reverse($filtered, true);
} elseif ($sort === 'price_low') {
    uasort($filtered, static fn(array $left, array $right): int => ($left['price'] <=> $right['price']) ?: ($left['id'] <=> $right['id']));
} elseif ($sort === 'price_high') {
    uasort($filtered, static fn(array $left, array $right): int => ($right['price'] <=> $left['price']) ?: ($left['id'] <=> $right['id']));
} elseif ($sort === 'rating') {
    uasort($filtered, static fn(array $left, array $right): int =>
        (($ratings[$right['id']]['average'] ?? 0) <=> ($ratings[$left['id']]['average'] ?? 0))
        ?: (($ratings[$right['id']]['count'] ?? 0) <=> ($ratings[$left['id']]['count'] ?? 0))
        ?: ($left['id'] <=> $right['id'])
    );
}
if ($searchError === '' && $filterError === '' && $search !== '') {
    record_search_analytics($search, count($filtered));
}
$wishlist = wishlist_ids();
$comparison = comparison_ids();
$featuredCoupons = available_coupons(3);
page_header('প্রতিদিনের পছন্দ');
?>
<main>
    <section class="hero">
        <div class="hero-copy">
            <span class="eyebrow"><span class="eyebrow-dot"></span> আপনার প্রতিদিন, একটু সুন্দর</span>
            <h1>পছন্দের সবকিছু,<br><em>এক ঠিকানায়।</em></h1>
            <p>ফ্যাশন থেকে ঘর, টেক থেকে নিজের যত্ন—আপনার ভালো লাগার জিনিসগুলো খুঁজে নিন সহজেই।</p>
            <a class="button button-dark" href="#products">কেনাকাটা শুরু করুন <span>↗</span></a>
            <div class="hero-note"><span class="note-stars">★★★★★</span><span>সুন্দর সব দৈনন্দিন পছন্দ</span></div>
        </div>
        <div class="hero-art">
            <div class="hero-image"><img src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1200&q=90" alt="সুন্দরভাবে সাজানো একটি দোকান"></div>
            <div class="hero-sticker"><strong>নতুন</strong><span>কিছু ভালো<br>লাগা এসেছে</span><b>✳</b></div>
            <div class="hero-caption">ভালো জিনিস। ভালো অনুভূতি।</div>
        </div>
        <div class="hero-side-label">ALL THINGS, WELL CHOSEN — EST. 2024</div>
    </section>

    <section class="benefits" aria-label="আমাদের সুবিধা">
        <div><span class="benefit-icon">↗</span><span><b>সহজ ডেলিভারি</b><small>সারা বাংলাদেশে</small></span></div>
        <div><span class="benefit-icon">◇</span><span><b>বাছাই করা পণ্য</b><small>মানের নিশ্চয়তা</small></span></div>
        <div><span class="benefit-icon">♡</span><span><b>সহজ রিটার্ন</b><small>৭ দিনের মধ্যে</small></span></div>
        <div><span class="benefit-icon">৳</span><span><b>নিরাপদ পেমেন্ট</b><small>ক্যাশ অন ডেলিভারি ও bKash</small></span></div>
    </section>

    <?php if ($featuredCoupons !== []): ?>
        <section class="promo-section" aria-labelledby="promo-heading">
            <div class="promo-section-heading"><div><span class="eyebrow">শুধু আপনার জন্য</span><h2 id="promo-heading">চলতি <em>অফার</em></h2></div><a href="offers.php">সব অফার দেখুন ↗</a></div>
            <div class="promo-grid">
                <?php foreach ($featuredCoupons as $coupon): ?>
                    <article class="promo-card">
                        <span class="promo-discount"><?= $coupon['discount_type'] === 'percent' ? (int) $coupon['discount_value'] . '% ছাড়' : taka((int) $coupon['discount_value']) . ' ছাড়' ?></span>
                        <div class="promo-code-row"><code><?= e($coupon['code']) ?></code><button class="promo-copy-button" type="button" data-copy-code="<?= e($coupon['code']) ?>">কোড কপি</button></div>
                        <p><?= (int) $coupon['minimum_order'] > 0 ? taka((int) $coupon['minimum_order']) . '+ টাকার অর্ডারে' : 'সব অর্ডারে' ?> প্রযোজ্য<?php if ($coupon['maximum_discount'] !== null): ?> · সর্বোচ্চ ছাড় <?= taka((int) $coupon['maximum_discount']) ?><?php endif; ?></p>
                        <span class="promo-copy-status" aria-live="polite"></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="shop-section" id="products">
        <div class="section-heading">
            <div><span class="eyebrow">আপনার জন্য বাছাই করা</span><h2>পছন্দের <em>কালেকশন</em></h2></div>
            <div class="search-tools"><form class="search-form" method="get" action="index.php">
                <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
                <?php if ($sort !== 'featured'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
                <?php if ($minimumPriceInput !== ''): ?><input type="hidden" name="min_price" value="<?= e($minimumPriceInput) ?>"><?php endif; ?>
                <?php if ($maximumPriceInput !== ''): ?><input type="hidden" name="max_price" value="<?= e($maximumPriceInput) ?>"><?php endif; ?>
                <?php if ($inStockOnly): ?><input type="hidden" name="in_stock" value="1"><?php endif; ?>
                <label class="sr-only" for="search">পণ্য খুঁজুন</label><input id="search" name="q" list="search-suggestions" maxlength="80" value="<?= e($search) ?>" placeholder="বাংলা / English / Banglish-এ খুঁজুন"><datalist id="search-suggestions"><?php foreach ($searchSuggestions as $suggestion): ?><option value="<?= e($suggestion) ?>"><?php endforeach; ?></datalist>
                <button aria-label="খুঁজুন" type="submit">⌕</button>
            </form><small class="search-privacy-note">সার্চের aggregate তথ্য ৭ দিন রাখা হয়</small></div>
        </div>
        <div class="category-row">
            <?php $categoryParams = array_filter(['q' => $search, 'sort' => $sort, 'min_price' => $minimumPriceInput, 'max_price' => $maximumPriceInput, 'in_stock' => $inStockOnly ? '1' : ''], static fn(string $value): bool => $value !== ''); ?>
            <a class="category-pill <?= $category === '' ? 'active' : '' ?>" href="index.php<?= $categoryParams === [] ? '' : '?' . e(http_build_query($categoryParams)) ?>#products">সব পণ্য <span><?= count($allProducts) ?></span></a>
            <?php foreach ($categories as $item): ?>
                <?php $itemParams = ['category' => (string) $item] + $categoryParams; ?>
                <a class="category-pill <?= $category === $item ? 'active' : '' ?>" href="index.php?<?= e(http_build_query($itemParams)) ?>#products"><?= e(category_label((string) $item)) ?></a>
            <?php endforeach; ?>
        </div>
        <form class="catalog-filters" method="get" action="index.php">
            <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
            <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
            <label>সাজান<select name="sort">
                <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>বাছাই করা</option>
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>নতুন আগে</option>
                <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>দাম: কম থেকে বেশি</option>
                <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>দাম: বেশি থেকে কম</option>
                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>রেটিং অনুযায়ী</option>
            </select></label>
            <label>দাম (৳)<span class="price-range-fields"><input type="number" name="min_price" min="0" max="100000000" placeholder="সর্বনিম্ন" value="<?= e($minimumPriceInput) ?>"><span>—</span><input type="number" name="max_price" min="0" max="100000000" placeholder="সর্বোচ্চ" value="<?= e($maximumPriceInput) ?>"></span></label>
            <label class="stock-filter"><input type="checkbox" name="in_stock" value="1" <?= $inStockOnly ? 'checked' : '' ?>> শুধু স্টকে থাকা</label>
            <button class="button button-dark" type="submit">ফিল্টার করুন</button>
            <?php if ($category !== '' || $search !== '' || $minimumPriceInput !== '' || $maximumPriceInput !== '' || $inStockOnly || $sort !== 'featured'): ?><a class="filter-reset" href="index.php#products">ফিল্টার মুছুন</a><?php endif; ?>
        </form>
        <?php if ($searchError !== ''): ?><p class="form-error"><?= e($searchError) ?></p><?php endif; ?>
        <?php if ($filterError !== ''): ?><p class="form-error"><?= e($filterError) ?></p><?php endif; ?>
        <?php if ($search !== '' || $category !== '' || $minimumPriceInput !== '' || $maximumPriceInput !== '' || $inStockOnly): ?>
            <p class="results-note"><?= count($filtered) ?>টি পণ্য পাওয়া গেছে<?= $search !== '' ? ' · “' . e($search) . '”' : '' ?></p>
        <?php endif; ?>
        <div class="product-grid">
            <?php foreach ($filtered as $id => $product): ?>
                <article class="product-card">
                    <a class="product-image" href="product.php?id=<?= (int) $id ?>" aria-label="<?= e($product['name']) ?> বিস্তারিত">
                        <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                        <?php if ($product['badge'] !== ''): ?><span class="product-badge"><?= e($product['badge']) ?></span><?php endif; ?>
                    </a>
                    <div class="product-info"><div class="product-meta"><span><?= e(category_label($product['category'])) ?></span><span class="rating"><?= isset($ratings[$id]) ? '★ ' . number_format($ratings[$id]['average'], 1) . ' (' . $ratings[$id]['count'] . ')' : 'নতুন পণ্য' ?></span></div>
                        <h3><a href="product.php?id=<?= (int) $id ?>"><?= e($product['name']) ?></a></h3>
                        <div class="price"><?= taka($product['price']) ?> <?php if ($product['old_price']): ?><del><?= taka($product['old_price']) ?></del><?php endif; ?></div>
                        <?php $availableStock = product_available_stock($product); if ($availableStock !== null): ?><small class="product-stock <?= $availableStock === 0 ? 'stock-warning' : '' ?>"><?= $availableStock === 0 ? 'স্টক শেষ' : 'স্টকে ' . $availableStock . 'টি' ?></small><?php endif; ?>
                        <div class="product-tool-actions">
                            <form class="wishlist-toggle-form" method="post" action="wishlist.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="toggle"><button class="wishlist-toggle <?= in_array($id, $wishlist, true) ? 'is-saved' : '' ?>" type="submit" aria-label="<?= in_array($id, $wishlist, true) ? 'পছন্দের তালিকা থেকে সরান' : 'পছন্দের তালিকায় যোগ করুন' ?>"><?= in_array($id, $wishlist, true) ? '♥' : '♡' ?></button></form>
                            <form class="comparison-toggle-form" method="post" action="compare.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="<?= in_array($id, $comparison, true) ? 'remove' : 'add' ?>"><button class="comparison-toggle" type="submit" <?= count($comparison) >= 3 && !in_array($id, $comparison, true) ? 'disabled title="একসঙ্গে সর্বোচ্চ ৩টি পণ্য তুলনা করা যাবে"' : '' ?>><?= in_array($id, $comparison, true) ? '✓ তুলনায় আছে' : '⇄ তুলনা' ?></button></form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (count($filtered) === 0): ?><div class="empty-search"><span>⌕</span><h3>কোনো পণ্য পাওয়া যায়নি</h3><p>অন্য শব্দ দিয়ে খুঁজে দেখুন, অথবা সব পণ্য দেখুন।</p><a class="button button-dark" href="index.php#products">সব পণ্য দেখুন</a></div><?php endif; ?>
    </section>

    <section class="newsletter" id="newsletter"><div><span class="eyebrow">ALL SHOP LETTER</span><h2>নতুন পছন্দের খবর<br><em>সবার আগে পান।</em></h2></div><form method="post" action="index.php#newsletter"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="newsletter"><label class="sr-only" for="email">আপনার ইমেইল</label><input id="email" name="email" type="email" placeholder="আপনার ইমেইল ঠিকানা" required><button type="submit">সাবস্ক্রাইব করুন <span>↗</span></button><small class="newsletter-message"><?= e($newsletterMessage ?? 'শুধু ভালো খবর, কোনো স্প্যাম নয়।') ?></small></form><span class="newsletter-flower">✳</span></section>
</main>
<?php page_footer(); ?>
