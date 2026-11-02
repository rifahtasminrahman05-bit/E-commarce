<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('comparison', 'সেশন যাচাই করা যায়নি। আবার চেষ্টা করুন।');
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $ids = comparison_ids();
        if ($action === 'clear') {
            unset($_SESSION['comparison']);
            flash('comparison', 'তুলনার তালিকা খালি করা হয়েছে।');
        } elseif (in_array($action, ['add', 'remove'], true)) {
            $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                flash('comparison', 'সঠিক পণ্য বাছাই করুন।');
            } elseif ($action === 'remove') {
                $_SESSION['comparison'] = array_values(array_diff($ids, [$id]));
                flash('comparison', 'পণ্যটি তুলনার তালিকা থেকে সরানো হয়েছে।');
            } elseif (product_by_id($id) === null) {
                flash('comparison', 'পণ্যটি পাওয়া যায়নি বা আর বিক্রির জন্য নেই।');
            } elseif (in_array($id, $ids, true)) {
                flash('comparison', 'পণ্যটি ইতিমধ্যে তুলনার তালিকায় আছে।');
            } elseif (count($ids) >= 3) {
                flash('comparison', 'একসঙ্গে সর্বোচ্চ ৩টি পণ্য তুলনা করা যাবে।');
            } else {
                $ids[] = $id;
                $_SESSION['comparison'] = $ids;
                flash('comparison', 'পণ্যটি তুলনার তালিকায় যোগ হয়েছে।');
            }
        } else {
            flash('comparison', 'অনুরোধটি সঠিক নয়। আবার চেষ্টা করুন।');
        }
    }

    redirect('compare.php#compare');
}

$notice = flash('comparison');
$comparedProducts = [];
$validIds = [];
foreach (comparison_ids() as $id) {
    $product = product_by_id($id);
    if ($product !== null) {
        $comparedProducts[] = $product;
        $validIds[] = $id;
    }
}
$_SESSION['comparison'] = $validIds;
$ratings = product_ratings($validIds);
page_header('পণ্য তুলনা');
?>
<main class="inner-page comparison-page" id="compare">
    <div class="breadcrumb"><a href="index.php">হোম</a><span>/</span><span>পণ্য তুলনা</span></div>
    <div class="inner-heading"><div><span class="eyebrow">পছন্দের পণ্যগুলো পাশাপাশি দেখুন</span><h1>পণ্য <em>তুলনা</em></h1></div><span class="item-count"><?= count($comparedProducts) ?>/৩টি পণ্য</span></div>
    <?php if ($notice !== null): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($comparedProducts === []): ?>
        <div class="empty-cart"><span class="empty-cart-icon">⇄</span><h2>তুলনার তালিকা এখনো খালি</h2><p>ক্যাটালগে “তুলনা” বাটনে চাপ দিয়ে সর্বোচ্চ ৩টি পণ্য এখানে পাশাপাশি দেখুন।</p><a class="button button-dark" href="index.php#products">পণ্য বাছাই করুন <span>↗</span></a></div>
    <?php else: ?>
        <div class="comparison-toolbar"><p>দাম, রেটিং ও স্টক একসঙ্গে মিলিয়ে দেখুন।</p><form method="post" action="compare.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="clear"><button class="comparison-clear" type="submit">সব সরান</button></form></div>
        <div class="comparison-scroll">
            <table class="comparison-table">
                <tbody>
                    <tr><th scope="row">পণ্য</th><?php foreach ($comparedProducts as $product): ?><td><div class="comparison-product"><a href="product.php?id=<?= $product['id'] ?>"><img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" loading="lazy"><strong><?= e($product['name']) ?></strong></a><form method="post" action="compare.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= $product['id'] ?>"><input type="hidden" name="action" value="remove"><button class="comparison-remove" type="submit">তালিকা থেকে সরান</button></form></div></td><?php endforeach; ?></tr>
                    <tr><th scope="row">দাম</th><?php foreach ($comparedProducts as $product): ?><td><strong class="comparison-price"><?= taka($product['price']) ?></strong><?php if ($product['old_price'] !== null): ?> <del><?= taka($product['old_price']) ?></del><?php endif; ?></td><?php endforeach; ?></tr>
                    <tr><th scope="row">রেটিং</th><?php foreach ($comparedProducts as $product): $rating = $ratings[$product['id']] ?? ['average' => 0, 'count' => 0]; ?><td><span class="comparison-rating"><?= $rating['count'] > 0 ? '★ ' . number_format($rating['average'], 1) . ' / 5 · ' . $rating['count'] . 'টি রিভিউ' : 'এখনো রিভিউ নেই' ?></span></td><?php endforeach; ?></tr>
                    <tr><th scope="row">ক্যাটাগরি</th><?php foreach ($comparedProducts as $product): ?><td><?= e(category_label($product['category'])) ?></td><?php endforeach; ?></tr>
                    <tr><th scope="row">স্টক</th><?php foreach ($comparedProducts as $product): $stock = product_available_stock($product); ?><td class="<?= $stock === 0 ? 'stock-warning' : '' ?>"><?= $stock === null ? 'স্টক গণনা নেই' : ($stock === 0 ? 'স্টক শেষ' : 'স্টকে ' . $stock . 'টি') ?></td><?php endforeach; ?></tr>
                    <tr><th scope="row">বিবরণ</th><?php foreach ($comparedProducts as $product): ?><td class="comparison-description"><?= e($product['description']) ?></td><?php endforeach; ?></tr>
                    <tr><th scope="row">দেখুন</th><?php foreach ($comparedProducts as $product): ?><td><a class="button button-dark comparison-detail-link" href="product.php?id=<?= $product['id'] ?>">পণ্য বিস্তারিত <span>↗</span></a></td><?php endforeach; ?></tr>
                </tbody>
            </table>
        </div>
        <?php if (count($comparedProducts) < 3): ?><p class="comparison-hint">আরও <?= 3 - count($comparedProducts) ?>টি পর্যন্ত পণ্য যোগ করতে <a href="index.php#products">ক্যাটালগে ফিরে যান</a>।</p><?php endif; ?>
    <?php endif; ?>
</main>
<?php page_footer(); ?>
