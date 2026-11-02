<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

$order = null;
$items = [];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'সেশন যাচাই করা যায়নি। আবার চেষ্টা করুন।';
    } else {
        $orderNumber = strtoupper(trim((string) ($_POST['order_number'] ?? '')));
        $phone = preg_replace('/\s+/', '', trim((string) ($_POST['phone'] ?? ''))) ?? '';
        $statement = db()->prepare('SELECT * FROM orders WHERE order_number = :number AND phone = :phone');
        $statement->execute(['number' => $orderNumber, 'phone' => $phone]);
        $order = $statement->fetch() ?: null;
        if ($order === null) {
            $error = 'এই অর্ডার নম্বর ও ফোন নম্বর দিয়ে কোনো অর্ডার পাওয়া যায়নি।';
        } else {
            $lineItems = db()->prepare('SELECT product_name, variant_label, quantity, unit_price FROM order_items WHERE order_id = :id');
            $lineItems->execute(['id' => $order['id']]);
            $items = $lineItems->fetchAll();
        }
    }
}

$orderLabels = ['pending' => 'নতুন অর্ডার', 'processing' => 'প্রস্তুত হচ্ছে', 'shipped' => 'পথে আছে', 'delivered' => 'ডেলিভারি সম্পন্ন', 'cancelled' => 'বাতিল'];
$paymentLabels = ['unpaid' => 'পেমেন্ট বাকি', 'pending' => 'পেমেন্ট যাচাই হচ্ছে', 'paid' => 'পেমেন্ট সম্পন্ন', 'failed' => 'পেমেন্ট ব্যর্থ'];
page_header('অর্ডার ট্র্যাক');
?>
<main class="inner-page order-track-page">
    <div class="breadcrumb"><a href="index.php">হোম</a><span>/</span><span>অর্ডার ট্র্যাক</span></div>
    <div class="inner-heading"><div><span class="eyebrow">আপনার অর্ডারের খবর</span><h1>অর্ডার <em>ট্র্যাক করুন</em></h1></div></div>
    <div class="track-layout">
        <form class="checkout-panel track-form" method="post" action="order.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <p>অর্ডার নিশ্চিতকরণের সময় পাওয়া অর্ডার নম্বর ও ফোন নম্বর লিখুন।</p>
            <label>অর্ডার নম্বর<input type="text" name="order_number" required placeholder="AS241201ABCD" value="<?= e((string) ($_POST['order_number'] ?? '')) ?>"></label>
            <label>মোবাইল নম্বর<input type="tel" name="phone" required placeholder="01XXXXXXXXX" value="<?= e((string) ($_POST['phone'] ?? '')) ?>"></label>
            <?php if ($error !== ''): ?><p class="form-error"><?= e($error) ?></p><?php endif; ?>
            <button type="submit" class="button button-dark">অর্ডার খুঁজুন <span>↗</span></button>
        </form>
        <?php if ($order !== null): ?>
            <section class="order-track-card"><span class="eyebrow">অর্ডার <?= e($order['order_number']) ?></span><h2><?= e($orderLabels[$order['order_status']] ?? 'অর্ডার আপডেট') ?></h2><p><?= e($paymentLabels[$order['payment_status']] ?? 'পেমেন্ট আপডেট') ?> · <?= e($order['created_at']) ?></p>
                <div class="confirmation-items"><?php foreach ($items as $item): ?><div><span><?= e($item['product_name']) ?><?= $item['variant_label'] !== null ? ' · ' . e($item['variant_label']) : '' ?> × <?= (int) $item['quantity'] ?></span><strong><?= taka((int) $item['unit_price'] * (int) $item['quantity']) ?></strong></div><?php endforeach; ?></div>
                <div class="summary-row"><span>সাবটোটাল</span><span><?= taka((int) $order['subtotal']) ?></span></div>
                <?php if ((int) $order['discount'] > 0): ?><div class="summary-row discount-row"><span>কুপন ছাড় (<?= e((string) $order['coupon_code']) ?>)</span><span>−<?= taka((int) $order['discount']) ?></span></div><?php endif; ?>
                <div class="summary-row"><span>ডেলিভারি</span><span><?= (int) $order['delivery'] === 0 ? 'ফ্রি' : taka((int) $order['delivery']) ?></span></div>
                <div class="summary-total"><span>মোট</span><strong><?= taka((int) $order['total']) ?></strong></div>
            </section>
        <?php endif; ?>
    </div>
</main>
<?php page_footer(); ?>
