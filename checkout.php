<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

if (isset($_GET['bkash_callback'])) {
    $orderId = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT);
    $state = $_GET['state'] ?? null;
    $paymentId = $_GET['paymentID'] ?? null;
    $callbackStatus = strtolower((string) ($_GET['status'] ?? ''));
    $query = db()->prepare('SELECT * FROM orders WHERE id = :id AND payment_method = "bkash"');
    $query->execute(['id' => $orderId === false ? 0 : $orderId]);
    $order = $query->fetch();
    if (!$order || !is_string($state) || !is_string($order['callback_hash']) || !hash_equals($order['callback_hash'], hash('sha256', $state))) {
        http_response_code(400);
        exit('অর্ডার যাচাই করা যায়নি।');
    }

    if ($order['payment_status'] === 'paid') {
        $_SESSION['last_order_id'] = (int) $order['id'];
        redirect('checkout.php?complete=1');
    }
    if ($callbackStatus !== 'success' || !is_string($paymentId) || !hash_equals((string) $order['payment_id'], $paymentId)) {
        $update = db()->prepare('UPDATE orders SET payment_status = "failed" WHERE id = :id AND payment_status != "paid"');
        $update->execute(['id' => $order['id']]);
        release_coupon_reservation((int) $order['id']);
        release_order_stock((int) $order['id']);
        flash('checkout', $callbackStatus === 'cancel' ? 'পেমেন্ট বাতিল হয়েছে। কার্টে থাকা পণ্য দিয়ে আবার চেষ্টা করতে পারেন।' : 'পেমেন্ট সম্পন্ন হয়নি। কার্টে থাকা পণ্য দিয়ে আবার চেষ্টা করতে পারেন।');
        redirect('checkout.php');
    }

    try {
        try {
            $payment = bkash_execute_payment($paymentId);
        } catch (Throwable $executeError) {
            error_log('All Shop bKash execute request failed: ' . $executeError->getMessage());
            $payment = bkash_query_payment($paymentId);
        }
        $invoice = (string) ($payment['merchantInvoiceNumber'] ?? $payment['merchantInvoice'] ?? '');
        $paidAmount = (float) ($payment['amount'] ?? 0);
        if (
            ($payment['transactionStatus'] ?? '') !== 'Completed'
            || (string) ($payment['paymentId'] ?? '') !== $paymentId
            || $invoice !== $order['order_number']
            || strtoupper((string) ($payment['currency'] ?? '')) !== 'BDT'
            || abs($paidAmount - (int) $order['total']) > 0.001
            || !is_string($payment['trxId'] ?? null)
            || $payment['trxId'] === ''
        ) {
            throw new RuntimeException('bKash transaction verification did not match the order.');
        }

        $update = db()->prepare('UPDATE orders SET payment_status = "paid", transaction_id = :trx WHERE id = :id AND payment_id = :payment_id AND payment_status != "paid"');
        $update->execute([
            'trx' => (string) ($payment['trxId'] ?? ''),
            'id' => $order['id'],
            'payment_id' => $paymentId,
        ]);
        if ($update->rowCount() !== 1) {
            $paidQuery = db()->prepare('SELECT payment_status FROM orders WHERE id = :id');
            $paidQuery->execute(['id' => $order['id']]);
            if ($paidQuery->fetchColumn() !== 'paid') {
                throw new RuntimeException('The verified payment could not be saved.');
            }
        }
        if (isset($_SESSION['pending_cart_snapshots'][$order['id']]) && $_SESSION['cart'] === $_SESSION['pending_cart_snapshots'][$order['id']]) {
            unset($_SESSION['cart']);
        }
        unset($_SESSION['pending_cart_snapshots'][$order['id']]);
        $_SESSION['last_order_id'] = (int) $order['id'];
        redirect('checkout.php?complete=1');
    } catch (Throwable $error) {
        error_log('All Shop bKash callback verification failed for order ' . $order['order_number'] . ': ' . $error->getMessage());
        flash('checkout', 'পেমেন্টের অবস্থা নিশ্চিত করা যাচ্ছে না। অর্ডার নম্বর ' . $order['order_number'] . ' সংরক্ষিত আছে—আবার পেমেন্ট করার আগে সাপোর্টে যোগাযোগ করুন।');
        redirect('checkout.php');
    }
}

$errors = [];
$name = '';
$phone = '';
$address = '';
$paymentMethod = 'cod';
$notice = flash('checkout');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $paymentMethod = (string) ($_POST['payment'] ?? 'cod');
    $items = cart_items();
    $subtotal = cart_subtotal();
    $coupon = cart_coupon();
    $discount = $coupon['discount_amount'] ?? 0;
    $delivery = delivery_fee($subtotal);

    if (!verify_csrf()) {
        $errors[] = 'সেশন যাচাই করা যায়নি। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।';
    }
    $submittedNonce = $_POST['checkout_nonce'] ?? null;
    if (!is_string($submittedNonce) || !hash_equals(checkout_nonce(), $submittedNonce)) {
        $errors[] = 'এই checkout ফর্মটি আগেই ব্যবহার করা হয়েছে। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।';
    }
    if ($name === '' || text_length($name) > 100) {
        $errors[] = 'আপনার সঠিক নাম লিখুন।';
    }
    if (!preg_match('/^[0-9+()\-\s]{8,20}$/', $phone)) {
        $errors[] = 'সঠিক ফোন নম্বর লিখুন।';
    }
    if ($address === '' || text_length($address) > 500) {
        $errors[] = 'সম্পূর্ণ ডেলিভারি ঠিকানা লিখুন।';
    }
    if (!in_array($paymentMethod, ['cod', 'bkash'], true)) {
        $errors[] = 'একটি বৈধ পেমেন্ট পদ্ধতি বেছে নিন।';
    }
    if ($items === []) {
        $errors[] = 'আপনার কার্টে কোনো পণ্য নেই।';
    } elseif (!cart_stock_available()) {
        $errors[] = 'কার্টের কোনো পণ্য বা ভ্যারিয়েন্টের স্টক পরিবর্তিত হয়েছে। কার্ট আপডেট করে আবার চেষ্টা করুন।';
    }
    if ($paymentMethod === 'bkash' && !bkash_checkout_enabled()) {
        $errors[] = 'bKash পেমেন্ট এই মুহূর্তে উপলভ্য নয়। ক্যাশ অন ডেলিভারি বেছে নিন, অথবা অ্যাডমিনকে জানান।';
        $paymentMethod = 'cod';
    } elseif ($paymentMethod === 'bkash' && $errors === []) {
        try {
            bkash_configuration();
        } catch (Throwable $error) {
            error_log('All Shop bKash configuration error: ' . $error->getMessage());
            $errors[] = 'bKash পেমেন্ট এখনো কনফিগার করা হয়নি। ক্যাশ অন ডেলিভারি বেছে নিন, অথবা অ্যাডমিনকে জানান।';
        }
    }

    if ($errors === []) {
        $orderNumber = 'AS' . date('ymd') . strtoupper(bin2hex(random_bytes(8)));
        $callbackState = $paymentMethod === 'bkash' ? bin2hex(random_bytes(32)) : null;
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $insert = $pdo->prepare(
                'INSERT INTO orders (order_number, callback_hash, customer_name, phone, address, payment_method, payment_status, subtotal, coupon_code, discount, delivery, total)
                 VALUES (:number, :callback_hash, :name, :phone, :address, :method, :payment_status, :subtotal, :coupon_code, :discount, :delivery, :total)'
            );
            $insert->execute([
                'number' => $orderNumber,
                'callback_hash' => $callbackState === null ? null : hash('sha256', $callbackState),
                'name' => $name,
                'phone' => preg_replace('/\s+/', '', $phone),
                'address' => $address,
                'method' => $paymentMethod,
                'payment_status' => $paymentMethod === 'bkash' ? 'pending' : 'unpaid',
                'subtotal' => $subtotal,
                'coupon_code' => $coupon['code'] ?? null,
                'discount' => $discount,
                'delivery' => $delivery,
                'total' => $subtotal - $discount + $delivery,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $reserveProduct = $pdo->prepare(
                'UPDATE products SET stock_quantity = stock_quantity - :quantity
                 WHERE id = :id AND active = 1 AND track_inventory = 1 AND stock_quantity >= :quantity'
            );
            $reserveVariant = $pdo->prepare(
                'UPDATE product_variants SET stock_quantity = stock_quantity - :quantity
                 WHERE id = :variant_id AND product_id = :product_id AND active = 1 AND stock_quantity >= :quantity'
            );
            $reserveVariantTotal = $pdo->prepare(
                'UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :id'
            );
            $lineInsert = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, variant_id, variant_label, product_name, quantity, unit_price, stock_reserved)
                 VALUES (:order_id, :product_id, :variant_id, :variant_label, :name, :quantity, :unit_price, :stock_reserved)'
            );
            foreach ($items as $item) {
                $stockReserved = 0;
                if ($item['variant_id'] !== null) {
                    $reserveVariant->execute([
                        'quantity' => $item['quantity'],
                        'variant_id' => $item['variant_id'],
                        'product_id' => $item['id'],
                    ]);
                    if ($reserveVariant->rowCount() !== 1) {
                        throw new RuntimeException('The requested stock is no longer available.');
                    }
                    $reserveVariantTotal->execute(['quantity' => $item['quantity'], 'id' => $item['id']]);
                    $stockReserved = 1;
                } else {
                    $stockStatus = $pdo->prepare(
                        'SELECT track_inventory,
                                EXISTS (SELECT 1 FROM product_variants WHERE product_id = products.id AND active = 1) AS has_variants
                         FROM products WHERE id = :id AND active = 1'
                    );
                    $stockStatus->execute(['id' => $item['id']]);
                    $currentProduct = $stockStatus->fetch();
                    if (!$currentProduct || (bool) $currentProduct['has_variants']) {
                        throw new RuntimeException('The requested stock is no longer available.');
                    }
                    if ((int) $currentProduct['track_inventory'] === 1) {
                        $reserveProduct->execute(['quantity' => $item['quantity'], 'id' => $item['id']]);
                        if ($reserveProduct->rowCount() !== 1) {
                            throw new RuntimeException('The requested stock is no longer available.');
                        }
                        $stockReserved = 1;
                    }
                }
                $lineInsert->execute([
                    'order_id' => $orderId,
                    'product_id' => $item['id'],
                    'variant_id' => $item['variant_id'],
                    'variant_label' => $item['variant_label'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'stock_reserved' => $stockReserved,
                ]);
            }
            if ($coupon !== null) {
                $reserveCoupon = $pdo->prepare(
                    'UPDATE coupons SET usage_count = usage_count + 1
                     WHERE id = :id AND active = 1
                       AND (usage_limit IS NULL OR usage_count < usage_limit)'
                );
                $reserveCoupon->execute(['id' => $coupon['id']]);
                if ($reserveCoupon->rowCount() !== 1) {
                    throw new RuntimeException('The coupon is no longer available.');
                }
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof RuntimeException && $error->getMessage() === 'The coupon is no longer available.') {
                $errors[] = 'এই কুপনটির ব্যবহারের সীমা পূর্ণ হয়েছে। কুপন সরিয়ে আবার চেষ্টা করুন।';
            } elseif ($error instanceof RuntimeException && $error->getMessage() === 'The requested stock is no longer available.') {
                $errors[] = 'দুঃখিত, কার্টের কোনো পণ্যের স্টক শেষ বা পরিবর্তিত হয়েছে। কার্ট আপডেট করে আবার চেষ্টা করুন।';
            } else {
                throw $error;
            }
        }
        if ($errors === []) {
            $_SESSION['checkout_nonce'] = bin2hex(random_bytes(32));

            if ($paymentMethod === 'cod') {
                unset($_SESSION['cart'], $_SESSION['coupon_code']);
                $_SESSION['last_order_id'] = $orderId;
                redirect('checkout.php?complete=1');
            }

            $_SESSION['pending_cart_snapshots'][$orderId] = $_SESSION['cart'];
            try {
                $order = ['id' => $orderId, 'order_number' => $orderNumber, 'total' => $subtotal - $discount + $delivery];
                $response = bkash_create_payment($order, (string) $callbackState);
                $paymentId = $response['paymentId'] ?? null;
                $paymentUrl = $response['bkashURL'] ?? $response['bKashURL'] ?? null;
                $paymentHost = is_string($paymentUrl) ? parse_url($paymentUrl, PHP_URL_HOST) : null;
                $allowedHosts = config('ALLSHOP_BKASH_ENV', 'sandbox') === 'live'
                    ? ['payment.bkash.com']
                    : ['sandbox.payment.bkash.com', 'payment.bkash.com'];
                if (!is_string($paymentId) || !is_string($paymentUrl) || parse_url($paymentUrl, PHP_URL_SCHEME) !== 'https' || !in_array($paymentHost, $allowedHosts, true)) {
                    throw new RuntimeException('bKash returned an invalid payment redirect.');
                }
                $save = $pdo->prepare('UPDATE orders SET payment_id = :payment_id WHERE id = :id');
                $save->execute(['payment_id' => $paymentId, 'id' => $orderId]);
                header('Location: ' . $paymentUrl, true, 303);
                exit;
            } catch (Throwable $error) {
                error_log('All Shop bKash payment creation failed for order ' . $orderNumber . ': ' . $error->getMessage());
                $update = $pdo->prepare('UPDATE orders SET payment_status = "failed" WHERE id = :id');
                $update->execute(['id' => $orderId]);
                release_coupon_reservation($orderId);
                release_order_stock($orderId);
                unset($_SESSION['pending_cart_snapshots'][$orderId]);
                flash('checkout', 'bKash পেমেন্ট শুরু করা যায়নি। কার্টের পণ্য অক্ষত আছে—আবার চেষ্টা করুন অথবা ক্যাশ অন ডেলিভারি বেছে নিন।');
                redirect('checkout.php');
            }
        }
    }
}

$completedOrder = null;
if (isset($_GET['complete'], $_SESSION['last_order_id'])) {
    $query = db()->prepare('SELECT * FROM orders WHERE id = :id');
    $query->execute(['id' => $_SESSION['last_order_id']]);
    $completedOrder = $query->fetch() ?: null;
}

if ($completedOrder !== null) {
    $lineItems = db()->prepare('SELECT product_name, variant_label, quantity, unit_price FROM order_items WHERE order_id = :id');
    $lineItems->execute(['id' => $completedOrder['id']]);
    $orderItems = $lineItems->fetchAll();
    page_header('অর্ডার নিশ্চিত');
    ?>
    <main class="inner-page confirmation-page">
        <div class="confirmation-card"><div class="confirmation-check">✓</div><span class="eyebrow"><?= $completedOrder['payment_status'] === 'paid' || $completedOrder['payment_method'] === 'cod' ? 'অর্ডার সফলভাবে গ্রহণ করা হয়েছে' : 'পেমেন্ট যাচাই হচ্ছে' ?></span><h1>ধন্যবাদ, <em><?= e($completedOrder['customer_name']) ?>!</em></h1><p>অর্ডার নম্বরটি সংরক্ষণ করুন। ডেলিভারির আগে আমরা আপনার ফোনে যোগাযোগ করব।</p><div class="order-number">অর্ডার নম্বর <strong><?= e($completedOrder['order_number']) ?></strong></div>
            <div class="confirmation-items"><?php foreach ($orderItems as $item): ?><div><span><?= e($item['product_name']) ?><?= $item['variant_label'] !== null ? ' · ' . e($item['variant_label']) : '' ?> × <?= (int) $item['quantity'] ?></span><strong><?= taka((int) $item['unit_price'] * (int) $item['quantity']) ?></strong></div><?php endforeach; ?></div>
            <div class="summary-row"><span>সাবটোটাল</span><span><?= taka((int) $completedOrder['subtotal']) ?></span></div>
            <?php if ((int) $completedOrder['discount'] > 0): ?><div class="summary-row discount-row"><span>কুপন ছাড় (<?= e((string) $completedOrder['coupon_code']) ?>)</span><span>−<?= taka((int) $completedOrder['discount']) ?></span></div><?php endif; ?>
            <div class="summary-row"><span>ডেলিভারি</span><span><?= (int) $completedOrder['delivery'] === 0 ? 'ফ্রি' : taka((int) $completedOrder['delivery']) ?></span></div>
            <div class="summary-total"><span>সর্বমোট</span><strong><?= taka((int) $completedOrder['total']) ?></strong></div>
            <p class="confirmation-payment">পেমেন্ট: <?= $completedOrder['payment_method'] === 'bkash' ? 'bKash · ' . e($completedOrder['payment_status'] === 'paid' ? 'সম্পন্ন' : 'যাচাই হচ্ছে') : 'ক্যাশ অন ডেলিভারি' ?></p>
            <a class="button button-dark" href="order.php">অর্ডার ট্র্যাক করুন <span>↗</span></a>
        </div>
    </main>
    <?php
    page_footer();
    exit;
}

$items = cart_items();
if ($items === []) {
    redirect('cart.php');
}
$subtotal = cart_subtotal();
$coupon = cart_coupon();
$discount = $coupon['discount_amount'] ?? 0;
$delivery = delivery_fee($subtotal);
page_header('চেকআউট');
?>
<main class="inner-page">
    <div class="breadcrumb"><a href="index.php">হোম</a><span>/</span><a href="cart.php">কার্ট</a><span>/</span><span>চেকআউট</span></div>
    <div class="inner-heading"><div><span class="eyebrow">আর মাত্র এক ধাপ</span><h1>অর্ডার <em>সম্পন্ন করুন</em></h1></div></div>
    <?php if ($notice !== null): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($errors !== []): ?><div class="form-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form class="checkout-layout" method="post" action="checkout.php">
        <section class="checkout-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="checkout_nonce" value="<?= e(checkout_nonce()) ?>">
            <div class="checkout-panel"><h2><span>01</span> ডেলিভারি তথ্য</h2>
                <label>আপনার নাম<input type="text" name="name" value="<?= e($name) ?>" maxlength="100" autocomplete="name" required></label>
                <label>মোবাইল নম্বর<input type="tel" name="phone" value="<?= e($phone) ?>" maxlength="20" placeholder="01XXXXXXXXX" autocomplete="tel" required></label>
                <label>সম্পূর্ণ ঠিকানা<textarea name="address" rows="3" maxlength="500" placeholder="বাড়ি, রোড, এলাকা, জেলা" autocomplete="street-address" required><?= e($address) ?></textarea></label>
            </div>
            <div class="checkout-panel"><h2><span>02</span> পেমেন্ট পদ্ধতি</h2>
                <label class="payment-option"><input type="radio" name="payment" value="cod" <?= $paymentMethod === 'cod' ? 'checked' : '' ?>><span><b>ক্যাশ অন ডেলিভারি</b><small>পণ্য হাতে পেয়ে পেমেন্ট করুন</small></span><i>৳</i></label>
                <?php if (bkash_checkout_enabled()): ?>
                    <label class="payment-option"><input type="radio" name="payment" value="bkash" <?= $paymentMethod === 'bkash' ? 'checked' : '' ?>><span><b>bKash</b><small>নিরাপদ bKash checkout-এ পেমেন্ট</small></span><i class="bkash-mark">b</i></label>
                <?php else: ?>
                    <p class="payment-unavailable">bKash পেমেন্ট আপাতত উপলভ্য নয়।</p>
                <?php endif; ?>
            </div>
        </section>
        <aside class="summary-card checkout-summary"><h2>আপনার অর্ডার</h2>
            <?php foreach ($items as $item): ?>
                <div class="checkout-product"><img src="<?= e($item['image']) ?>" alt=""><span><?= e($item['name']) ?><small>পরিমাণ: <?= $item['quantity'] ?></small></span><strong><?= taka($item['price'] * $item['quantity']) ?></strong></div>
            <?php endforeach; ?>
            <div class="summary-row"><span>সাবটোটাল</span><span><?= taka($subtotal) ?></span></div>
            <?php if ($discount > 0): ?><div class="summary-row discount-row"><span>কুপন ছাড় (<?= e($coupon['code']) ?>)</span><span>−<?= taka($discount) ?></span></div><?php endif; ?>
            <div class="summary-row"><span>ডেলিভারি</span><span><?= $delivery === 0 ? 'ফ্রি' : taka($delivery) ?></span></div>
            <div class="summary-total"><span>সর্বমোট</span><strong><?= taka($subtotal - $discount + $delivery) ?></strong></div>
            <button type="submit" class="button button-dark button-full">অর্ডার নিশ্চিত করুন <span>↗</span></button><p class="secure-note">◇ আপনার তথ্য নিরাপদে রাখা হবে</p>
        </aside>
    </form>
</main>
<?php page_footer(); ?>
