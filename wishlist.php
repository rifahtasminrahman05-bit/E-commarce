<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'সেশন যাচাই করা যায়নি। আবার চেষ্টা করুন।';
    } else {
        $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $action = (string) ($_POST['action'] ?? '');
        $product = $id === false ? null : product_by_id((int) $id);
        if ($id === false || $product === null || !in_array($action, ['toggle', 'remove', 'add_to_cart'], true)) {
            $error = 'পণ্যটি পাওয়া যায়নি।';
        } elseif ($action === 'add_to_cart') {
            if ($product['has_variants']) {
                flash('wishlist', 'কার্টে যোগ করতে পণ্য পেজে গিয়ে সাইজ/রং বেছে নিন।');
                redirect('product.php?id=' . (int) $id);
            }
            $error = add_to_cart((int) $id, 1) ?? '';
            if ($error === '') {
                flash('cart', 'পণ্যটি আপনার কার্টে যোগ হয়েছে।');
                redirect('cart.php');
            }
        } else {
            $items = wishlist_ids();
            if ($action === 'remove' || in_array((int) $id, $items, true)) {
                $_SESSION['wishlist'] = array_values(array_diff($items, [(int) $id]));
                flash('wishlist', 'পণ্যটি পছন্দের তালিকা থেকে সরানো হয়েছে।');
            } else {
                $_SESSION['wishlist'][] = (int) $id;
                flash('wishlist', 'পণ্যটি আপনার পছন্দের তালিকায় যোগ হয়েছে।');
            }
            redirect('wishlist.php');
        }
    }
}

$notice = flash('wishlist');
$savedProducts = [];
foreach (wishlist_ids() as $id) {
    $product = product_by_id($id);
    if ($product !== null) {
        $savedProducts[] = $product;
    }
}
page_header('পছন্দের তালিকা');
?>
<main class="inner-page wishlist-page">
    <div class="breadcrumb"><a href="index.php">হোম</a><span>/</span><span>পছন্দের তালিকা</span></div>
    <div class="inner-heading"><div><span class="eyebrow">আপনার পছন্দগুলো একসাথে</span><h1>পছন্দের <em>তালিকা</em></h1></div><span class="item-count"><?= count($savedProducts) ?>টি পণ্য</span></div>
    <?php if ($notice !== null): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?>
    <?php if ($savedProducts === []): ?>
        <div class="empty-cart"><span class="empty-cart-icon">♡</span><h2>এখনো কিছু সংরক্ষণ করেননি</h2><p>ভালো লাগা পণ্যগুলোতে হৃদয় চিহ্ন চাপলে এখানে পাবেন।</p><a class="button button-dark" href="index.php#products">পণ্য দেখুন <span>↗</span></a></div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($savedProducts as $product): ?>
                <article class="product-card wishlist-product">
                    <a class="product-image" href="product.php?id=<?= $product['id'] ?>"><img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>"><?php if ($product['badge'] !== ''): ?><span class="product-badge"><?= e($product['badge']) ?></span><?php endif; ?></a>
                    <div class="product-info"><div class="product-meta"><span><?= e(category_label($product['category'])) ?></span><span class="rating"><?= taka($product['price']) ?></span></div><h3><a href="product.php?id=<?= $product['id'] ?>"><?= e($product['name']) ?></a></h3>
                        <div class="wishlist-actions">
                            <form method="post" action="wishlist.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= $product['id'] ?>"><input type="hidden" name="action" value="add_to_cart"><button class="button button-dark" type="submit">কার্টে যোগ <span>↗</span></button></form>
                            <form method="post" action="wishlist.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= $product['id'] ?>"><input type="hidden" name="action" value="remove"><button class="wishlist-remove" type="submit" aria-label="পছন্দের তালিকা থেকে সরান">♡</button></form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php page_footer(); ?>
