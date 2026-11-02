<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$product = $id === false ? null : product_by_id((int) $id);
if ($product === null) {
    http_response_code(404);
    page_header('পণ্য পাওয়া যায়নি');
    echo '<main class="inner-page"><div class="empty-search"><span>⌕</span><h1>পণ্যটি পাওয়া যায়নি</h1><p>পণ্যটি সরানো হয়েছে, অথবা লিংকটি সঠিক নয়।</p><a class="button button-dark" href="index.php#products">সব পণ্য দেখুন</a></div></main>';
    page_footer();
    exit;
}

$reviewError = '';
$reviewNotice = flash('review');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    $orderNumber = strtoupper(trim((string) ($_POST['order_number'] ?? '')));
    $phone = preg_replace('/\s+/', '', trim((string) ($_POST['phone'] ?? ''))) ?? '';
    $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
    $comment = trim((string) ($_POST['comment'] ?? ''));
    if (!verify_csrf()) {
        $reviewError = 'সেশন যাচাই করা যায়নি। আবার চেষ্টা করুন।';
    } elseif ($rating === false || $rating < 1 || $rating > 5 || $comment === '' || text_length($comment) > 1000) {
        $reviewError = '১–৫ স্টারের রেটিং ও ১,০০০ অক্ষরের মধ্যে মতামত লিখুন।';
    } else {
        $verifiedOrder = db()->prepare(
            'SELECT o.id, o.customer_name FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.order_number = :number AND o.phone = :phone AND oi.product_id = :product_id
               AND (o.payment_status = "paid" OR o.order_status = "delivered")
             LIMIT 1'
        );
        $verifiedOrder->execute([
            'number' => $orderNumber,
            'phone' => $phone,
            'product_id' => $product['id'],
        ]);
        $order = $verifiedOrder->fetch();
        if ($order === false) {
            $reviewError = 'রিভিউ দিতে এই পণ্যটি কেনা ও ডেলিভারি করা বা পেমেন্ট সম্পন্ন হওয়া প্রয়োজন।';
        } else {
            try {
                $saveReview = db()->prepare(
                    'INSERT INTO product_reviews (product_id, order_id, customer_name, rating, comment)
                     VALUES (:product_id, :order_id, :name, :rating, :comment)'
                );
                $saveReview->execute([
                    'product_id' => $product['id'],
                    'order_id' => $order['id'],
                    'name' => $order['customer_name'],
                    'rating' => $rating,
                    'comment' => $comment,
                ]);
                flash('review', 'ধন্যবাদ! আপনার verified review অ্যাডমিন অনুমোদনের পর দেখা যাবে।');
                redirect('product.php?id=' . $product['id'] . '#reviews');
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    $reviewError = 'এই অর্ডার থেকে আপনি ইতিমধ্যে এই পণ্যের রিভিউ দিয়েছেন।';
                } else {
                    throw $error;
                }
            }
        }
    }
}

$rating = product_rating($product['id']);
$reviews = approved_reviews($product['id']);
$isSaved = in_array($product['id'], wishlist_ids(), true);
$comparison = comparison_ids();
$isCompared = in_array($product['id'], $comparison, true);
$variants = product_variants($product['id']);
$availableStock = product_available_stock($product);
$outOfStock = $product['has_variants']
    ? $availableStock === 0
    : ((int) $product['track_inventory'] === 1 && $availableStock === 0);
$related = array_slice(array_filter(products($product['category']), static fn(array $item): bool => $item['id'] !== $product['id']), 0, 4, true);
page_header($product['name']);
?>
<main class="inner-page">
    <div class="breadcrumb"><a href="index.php">হোম</a><span>/</span><a href="index.php?category=<?= urlencode($product['category']) ?>#products"><?= e(category_label($product['category'])) ?></a><span>/</span><span><?= e($product['name']) ?></span></div>
    <section class="product-detail">
        <div class="product-detail-image"><img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>"><?php if ($product['badge'] !== ''): ?><span class="product-badge"><?= e($product['badge']) ?></span><?php endif; ?></div>
        <div class="product-detail-copy"><span class="eyebrow"><?= e(category_label($product['category'])) ?> · ALL SHOP SELECT</span><h1><?= e($product['name']) ?></h1><div class="product-detail-rating"><span><?= str_repeat('★', (int) round($rating['average'])) ?><?= str_repeat('☆', 5 - (int) round($rating['average'])) ?></span> <?= $rating['count'] > 0 ? number_format($rating['average'], 1) . ' · ' . $rating['count'] . 'টি রিভিউ' : 'এখনো রিভিউ নেই' ?></div><div class="product-detail-price"><?= taka($product['price']) ?><?php if ($product['old_price']): ?><del><?= taka($product['old_price']) ?></del><?php endif; ?></div><p><?= e($product['description']) ?></p>
            <div class="product-promises"><span>✓ মান যাচাই করা</span><span>✓ সহজ রিটার্ন</span><span>✓ সারা দেশে ডেলিভারি</span></div>
            <div class="product-stock-status <?= $outOfStock ? 'stock-warning' : '' ?>"><?= $outOfStock ? 'এই পণ্যটি এখন স্টকে নেই।' : ($product['has_variants'] || (int) $product['track_inventory'] === 1 ? 'স্টকে ' . $availableStock . 'টি আছে।' : '') ?></div>
            <div class="product-buy-actions"><form method="post" action="cart.php" class="product-buy-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= $product['id'] ?>"><?php if ($product['has_variants']): ?><label class="variant-picker">সাইজ / রং<select name="variant_id" required><option value="">ভ্যারিয়েন্ট বাছুন</option><?php foreach ($variants as $variant): ?><option value="<?= $variant['id'] ?>" <?= $variant['stock_quantity'] === 0 ? 'disabled' : '' ?>><?= e($variant['label']) ?><?= $variant['stock_quantity'] === 0 ? ' — স্টক শেষ' : ' — ' . $variant['stock_quantity'] . 'টি' ?></option><?php endforeach; ?></select></label><?php endif; ?><label>পরিমাণ<input type="number" name="quantity" min="1" max="99" value="1"></label><button class="button button-dark" type="submit" <?= $outOfStock ? 'disabled' : '' ?>>কার্টে যোগ করুন <span>↗</span></button></form><form class="comparison-detail-form" method="post" action="compare.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= $product['id'] ?>"><input type="hidden" name="action" value="<?= $isCompared ? 'remove' : 'add' ?>"><button class="comparison-detail-toggle" type="submit" <?= count($comparison) >= 3 && !$isCompared ? 'disabled title="একসঙ্গে সর্বোচ্চ ৩টি পণ্য তুলনা করা যাবে"' : '' ?>><?= $isCompared ? '✓ তুলনায় আছে' : '⇄ তুলনা' ?></button></form><form method="post" action="wishlist.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= $product['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="wishlist-detail-toggle <?= $isSaved ? 'is-saved' : '' ?>" type="submit" aria-label="পছন্দের তালিকা"><?= $isSaved ? '♥' : '♡' ?></button></form></div>
            <div class="product-delivery-note">ডেলিভারি ২–৫ কর্মদিবস · ৳১,৫০০ বা তার বেশি অর্ডারে ডেলিভারি ফ্রি</div>
        </div>
    </section>
    <section class="reviews-section" id="reviews">
        <div class="section-heading"><div><span class="eyebrow">ক্রেতাদের অভিজ্ঞতা</span><h2>পণ্য <em>রিভিউ</em></h2></div><span class="review-average"><?= $rating['count'] > 0 ? '★ ' . number_format($rating['average'], 1) . ' / 5 · ' . $rating['count'] . 'টি' : 'প্রথম রিভিউটি আপনিই দিন' ?></span></div>
        <div class="reviews-layout">
            <div class="review-list">
                <?php if ($reviews === []): ?><div class="review-empty">এই পণ্যে এখনো অনুমোদিত রিভিউ নেই। কেনাকাটা করে আপনার অভিজ্ঞতা শেয়ার করুন।</div><?php endif; ?>
                <?php foreach ($reviews as $review): ?><article class="review-card"><div class="review-card-heading"><strong><?= e($review['customer_name']) ?></strong><span><?= str_repeat('★', (int) $review['rating']) . str_repeat('☆', 5 - (int) $review['rating']) ?></span></div><p><?= nl2br(e($review['comment'])) ?></p><small>যাচাইকৃত ক্রেতা · <?= e($review['created_at']) ?></small></article><?php endforeach; ?>
            </div>
            <form class="review-form" method="post" action="product.php?id=<?= $product['id'] ?>#reviews">
                <h3>আপনার রিভিউ দিন</h3><p>রিভিউটি দেওয়ার জন্য অর্ডার নম্বর ও অর্ডারের ফোন নম্বর দিয়ে কেনাকাটা যাচাই হবে।</p>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review">
                <?php if ($reviewNotice !== null): ?><p class="notice"><?= e($reviewNotice) ?></p><?php endif; ?>
                <?php if ($reviewError !== ''): ?><p class="form-error"><?= e($reviewError) ?></p><?php endif; ?>
                <label>অর্ডার নম্বর<input name="order_number" maxlength="24" required placeholder="AS..."></label>
                <label>অর্ডারের ফোন নম্বর<input name="phone" type="tel" maxlength="20" required placeholder="01XXXXXXXXX"></label>
                <label>রেটিং<select name="rating" required><option value="">রেটিং বাছুন</option><option value="5">★★★★★ — দারুণ</option><option value="4">★★★★☆ — ভালো</option><option value="3">★★★☆☆ — মোটামুটি</option><option value="2">★★☆☆☆ — ভালো হয়নি</option><option value="1">★☆☆☆☆ — খারাপ</option></select></label>
                <label>আপনার মতামত<textarea name="comment" rows="4" maxlength="1000" required placeholder="পণ্যটি সম্পর্কে আপনার অভিজ্ঞতা"></textarea></label>
                <button class="button button-dark" type="submit">রিভিউ জমা দিন <span>↗</span></button>
            </form>
        </div>
    </section>
    <?php if ($related !== []): ?>
    <section class="related-products"><div class="section-heading"><div><span class="eyebrow">আরও বেছে নিন</span><h2>একই <em>কালেকশন</em></h2></div><a class="text-button" href="index.php?category=<?= urlencode($product['category']) ?>#products">সব দেখুন ↗</a></div><div class="product-grid">
        <?php foreach ($related as $relatedProduct): ?><article class="product-card"><a class="product-image" href="product.php?id=<?= $relatedProduct['id'] ?>"><img src="<?= e($relatedProduct['image']) ?>" alt="<?= e($relatedProduct['name']) ?>" loading="lazy"></a><div class="product-info"><div class="product-meta"><span><?= e(category_label($relatedProduct['category'])) ?></span></div><h3><a href="product.php?id=<?= $relatedProduct['id'] ?>"><?= e($relatedProduct['name']) ?></a></h3><div class="price"><?= taka($relatedProduct['price']) ?></div></div></article><?php endforeach; ?>
    </div></section>
    <?php endif; ?>
</main>
<?php page_footer(); ?>
