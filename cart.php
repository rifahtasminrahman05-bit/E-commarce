<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

$error = '';
$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'সেশন যাচাই করা যায়নি। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।';
    } elseif (($_POST['action'] ?? '') === 'apply_coupon') {
        $code = strtoupper(trim((string) ($_POST['coupon_code'] ?? '')));
        $coupon = active_coupon($code);
        if ($coupon === null) {
            unset($_SESSION['coupon_code']);
            $error = 'কুপন কোডটি সঠিক নয়, মেয়াদ শেষ, অথবা ব্যবহারের সীমা পূর্ণ হয়েছে।';
        } elseif (cart_subtotal() < (int) $coupon['minimum_order']) {
            $error = 'এই কুপন পেতে কমপক্ষে ' . taka((int) $coupon['minimum_order']) . ' টাকার পণ্য লাগবে।';
        } else {
            $_SESSION['coupon_code'] = $coupon['code'];
            flash('cart', 'কুপন "' . $coupon['code'] . '" প্রয়োগ হয়েছে।');
            redirect('cart.php');
        }
    } elseif (($_POST['action'] ?? '') === 'remove_coupon') {
        unset($_SESSION['coupon_code']);
        flash('cart', 'কুপন সরানো হয়েছে।');
        redirect('cart.php');
    } elseif (isset($_POST['product_id'])) {
        $id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
        $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);
        $variantInput = trim((string) ($_POST['variant_id'] ?? ''));
        $variantId = $variantInput === '' ? null : filter_var($variantInput, FILTER_VALIDATE_INT);
        if ($id === false || $quantity === false || $variantId === false) {
            $error = 'পণ্যটি পাওয়া যায়নি অথবা পরিমাণটি সঠিক নয়।';
        } else {
            $error = add_to_cart((int) $id, (int) $quantity, $variantId === null ? null : (int) $variantId) ?? '';
            if ($error === '') {
                flash('cart', 'পণ্যটি আপনার কার্টে যোগ হয়েছে।');
                redirect('cart.php');
            }
        }
    } elseif (isset($_POST['remove'])) {
        $key = parse_cart_key($_POST['remove']);
        if ($key !== null) {
            unset($_SESSION['cart'][cart_key($key[0], $key[1])]);
            flash('cart', 'পণ্যটি কার্ট থেকে সরানো হয়েছে।');
            redirect('cart.php');
        }
    } elseif (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
        $currentItems = [];
        foreach (cart_items() as $item) {
            $currentItems[(string) $item['cart_key']] = $item;
        }
        $quantityUpdates = [];
        foreach ($_POST['quantities'] as $key => $quantity) {
            $cartKey = parse_cart_key((string) $key);
            $amount = filter_var($quantity, FILTER_VALIDATE_INT);
            $item = $currentItems[(string) $key] ?? null;
            if ($cartKey === null || $amount === false || $amount < 0 || $amount > 99 || $item === null) {
                $error = 'পণ্যের পরিমাণ ০ থেকে ৯৯-এর মধ্যে হতে হবে।';
                break;
            }
            if ($amount === 0) {
                $quantityUpdates[cart_key($cartKey[0], $cartKey[1])] = 0;
            } elseif (!$item['selection_available'] || ($item['stock_tracked'] && $amount > $item['available_stock'])) {
                $error = 'স্টকে মাত্র ' . $item['available_stock'] . 'টি আছে; কার্টের পরিমাণ কমিয়ে আবার চেষ্টা করুন।';
                break;
            } else {
                $quantityUpdates[cart_key($cartKey[0], $cartKey[1])] = $amount;
            }
        }
        if ($error === '') {
            foreach ($quantityUpdates as $key => $amount) {
                if ($amount === 0) {
                    unset($_SESSION['cart'][$key]);
                } else {
                    $_SESSION['cart'][$key] = $amount;
                }
            }
            flash('cart', 'কার্ট আপডেট করা হয়েছে।');
            redirect('cart.php');
        }
    }
}

$notice ??= flash('cart');
$items = cart_items();
$subtotal = cart_subtotal();
$coupon = cart_coupon();
$discount = $coupon['discount_amount'] ?? 0;
$delivery = delivery_fee($subtotal);
$freeDeliveryThreshold = free_delivery_threshold();
$freeDeliveryProgress = min(100, (int) floor($subtotal / $freeDeliveryThreshold * 100));
$freeDeliveryRemaining = max(0, $freeDeliveryThreshold - $subtotal);
$stockAvailable = cart_stock_available();
page_header('আপনার কার্ট');
?>
<main class="inner-page">
    <div class="breadcrumb"><a href="index.php">হোম</a><span>/</span><span>আপনার কার্ট</span></div>
    <div class="inner-heading"><div><span class="eyebrow">আপনার পছন্দ</span><h1>শপিং <em>কার্ট</em></h1></div><span class="item-count"><?= cart_count() ?>টি পণ্য</span></div>
    <?php if ($notice !== null): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?>
    <?php if ($items === []): ?>
        <div class="empty-cart"><span class="empty-cart-icon">♡</span><h2>আপনার কার্ট এখনো খালি</h2><p>পছন্দের কিছু পণ্য যোগ করে কেনাকাটা শুরু করুন।</p><a class="button button-dark" href="index.php#products">পণ্য দেখুন <span>↗</span></a></div>
    <?php else: ?>
        <div class="cart-layout">
            <form class="cart-items" method="post" action="cart.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <?php foreach ($items as $item): ?>
                    <article class="cart-item">
                        <a href="product.php?id=<?= $item['id'] ?>"><img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>"></a>
                        <div class="cart-item-details"><span class="product-category"><?= e(category_label($item['category'])) ?></span><h2><a href="product.php?id=<?= $item['id'] ?>"><?= e($item['name']) ?></a></h2><?php if ($item['variant_label'] !== null): ?><span class="cart-variant"><?= e($item['variant_label']) ?></span><?php endif; ?><span class="cart-unit-price"><?= taka($item['price']) ?></span><?php if (!$item['in_stock']): ?><small class="stock-warning">পছন্দের ভ্যারিয়েন্ট/পরিমাণটি এখন স্টকে নেই।</small><?php elseif ($item['stock_tracked']): ?><small class="stock-note">স্টকে <?= $item['available_stock'] ?>টি</small><?php endif; ?></div>
                        <label class="quantity-control">পরিমাণ<input type="number" min="0" max="99" name="quantities[<?= e((string) $item['cart_key']) ?>]" value="<?= $item['quantity'] ?>"></label>
                        <strong class="line-total"><?= taka($item['price'] * $item['quantity']) ?></strong>
                        <button class="remove-button" type="submit" name="remove" value="<?= e((string) $item['cart_key']) ?>" aria-label="<?= e($item['name']) ?> সরান">×</button>
                    </article>
                <?php endforeach; ?>
                <div class="cart-actions"><a href="index.php#products">← কেনাকাটা চালিয়ে যান</a><button type="submit" class="text-button">কার্ট আপডেট করুন</button></div>
            </form>
            <aside class="summary-card"><h2>অর্ডার সারাংশ</h2>
                <form class="coupon-form" method="post" action="cart.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="apply_coupon"><label class="sr-only" for="coupon-code">কুপন কোড</label><input id="coupon-code" name="coupon_code" maxlength="40" placeholder="কুপন কোড" value="<?= e($coupon['code'] ?? '') ?>" <?= $coupon !== null ? 'disabled' : '' ?>><button class="text-button" type="submit" <?= $coupon !== null ? 'disabled' : '' ?>>প্রয়োগ</button></form>
                <?php if ($coupon !== null): ?><div class="applied-coupon"><span><?= e($coupon['code']) ?> প্রয়োগ হয়েছে</span><form method="post" action="cart.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="remove_coupon"><button type="submit" aria-label="কুপন সরান">×</button></form></div><?php endif; ?>
                <div class="summary-row"><span>সাবটোটাল</span><span><?= taka($subtotal) ?></span></div>
                <?php if ($discount > 0): ?><div class="summary-row discount-row"><span>কুপন ছাড়</span><span>−<?= taka($discount) ?></span></div><?php endif; ?>
                <div class="shipping-progress">
                    <p><?= $freeDeliveryRemaining === 0 ? 'দারুণ! আপনার অর্ডারে ডেলিভারি ফ্রি।' : 'আর ' . taka($freeDeliveryRemaining) . ' টাকার পণ্য নিলেই ডেলিভারি ফ্রি।' ?></p>
                    <div class="shipping-progress-track" role="progressbar" aria-label="ফ্রি ডেলিভারির অগ্রগতি" aria-valuemin="0" aria-valuemax="<?= $freeDeliveryThreshold ?>" aria-valuenow="<?= min($subtotal, $freeDeliveryThreshold) ?>"><span style="width: <?= $freeDeliveryProgress ?>%"></span></div>
                </div>
                <div class="summary-row"><span>ডেলিভারি</span><span><?= $delivery === 0 ? 'ফ্রি' : taka($delivery) ?></span></div><div class="summary-total"><span>মোট</span><strong><?= taka($subtotal - $discount + $delivery) ?></strong></div><?php if (!$stockAvailable): ?><p class="stock-warning">কার্টের পণ্যের স্টক আপডেট করুন বা অনুপলব্ধ ভ্যারিয়েন্ট সরান।</p><?php endif; ?><a class="button button-dark button-full <?= !$stockAvailable ? 'button-disabled' : '' ?>" href="<?= $stockAvailable ? 'checkout.php' : '#cart' ?>" <?= !$stockAvailable ? 'aria-disabled="true"' : '' ?>>চেকআউটে যান <span>↗</span></a><p class="secure-note">◇ নিরাপদ চেকআউট · bKash অথবা ক্যাশ অন ডেলিভারি</p>
                <p class="coupon-hint">ALLSHOP10 কুপনে ৳১,০০০+ অর্ডারে ১০% ছাড়।</p>
            </aside>
        </div>
    <?php endif; ?>
</main>
<?php page_footer(); ?>
