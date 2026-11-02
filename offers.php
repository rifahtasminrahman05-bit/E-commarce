<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

$offers = available_coupons(50);
page_header('চলতি অফার');
?>
<main class="offers-page">
    <div class="breadcrumb"><a href="index.php">হোম</a><span>/</span><span>চলতি অফার</span></div>
    <div class="offers-heading">
        <span class="eyebrow">ALL SHOP PROMOTIONS</span>
        <h1>কেনাকাটায় <em>অতিরিক্ত সাশ্রয়</em></h1>
        <p>চলতি কুপন কোডটি কপি করে আপনার কার্টে প্রয়োগ করুন। প্রতিটি অফারের শর্ত আলাদা হতে পারে।</p>
    </div>
    <?php if ($offers === []): ?>
        <section class="offers-empty"><span>✳</span><h2>এই মুহূর্তে কোনো চলতি অফার নেই</h2><p>নতুন অফারের খবর পেতে আমাদের newsletter-এ যুক্ত থাকুন।</p><a class="button button-dark" href="index.php#newsletter">আপডেট পান <span>↗</span></a></section>
    <?php else: ?>
        <div class="promo-grid offers-grid">
            <?php foreach ($offers as $coupon): ?>
                <article class="promo-card offer-card">
                    <span class="promo-discount"><?= $coupon['discount_type'] === 'percent' ? (int) $coupon['discount_value'] . '% ছাড়' : taka((int) $coupon['discount_value']) . ' ছাড়' ?></span>
                    <div class="promo-code-row"><code><?= e($coupon['code']) ?></code><button class="promo-copy-button" type="button" data-copy-code="<?= e($coupon['code']) ?>">কোড কপি</button></div>
                    <p><?= (int) $coupon['minimum_order'] > 0 ? taka((int) $coupon['minimum_order']) . '+ টাকার অর্ডারে' : 'সব অর্ডারে' ?> প্রযোজ্য<?php if ($coupon['maximum_discount'] !== null): ?> · সর্বোচ্চ ছাড় <?= taka((int) $coupon['maximum_discount']) ?><?php endif; ?></p>
                    <?php if ($coupon['ends_at'] !== null): ?><small class="offer-expiry">মেয়াদ শেষ: <?= e(substr((string) $coupon['ends_at'], 0, 16)) ?></small><?php endif; ?>
                    <span class="promo-copy-status" aria-live="polite"></span>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="offers-cta"><p>কোড কপি করেছেন? এখন পছন্দের পণ্য বেছে নিন।</p><a class="button button-dark" href="index.php#products">পণ্য দেখুন <span>↗</span></a></div>
    <?php endif; ?>
</main>
<?php page_footer(); ?>
