<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

$seller = require_seller();
$viewInput = $_GET['view'] ?? 'dashboard';
$view = is_string($viewInput) ? $viewInput : 'dashboard';
if (!in_array($view, ['dashboard', 'product'], true)) {
    http_response_code(404);
    $view = 'dashboard';
}

$errors = [];
$notice = flash('seller');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'সেশন যাচাই করা যায়নি। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।';
    } else {
        $actionInput = $_POST['action'] ?? '';
        $action = is_string($actionInput) ? $actionInput : '';
        if ($action === 'logout') {
            unset($_SESSION['seller_id'], $_SESSION['seller_last_activity']);
            session_regenerate_id(true);
            redirect('seller_login.php');
        }

        if (in_array($action, ['save_product_draft', 'submit_product'], true)) {
            $idValue = $_POST['id'] ?? '';
            $idInput = is_string($idValue) ? trim($idValue) : '';
            $id = $idInput === '' ? null : filter_var($idInput, FILTER_VALIDATE_INT);
            $nameInput = $_POST['name'] ?? '';
            $categoryInput = $_POST['category'] ?? '';
            $priceInput = $_POST['price'] ?? '';
            $oldPriceValue = $_POST['old_price'] ?? '';
            $imageInput = $_POST['image'] ?? '';
            $descriptionInput = $_POST['description'] ?? '';
            $stockInput = $_POST['stock_quantity'] ?? '';
            $name = is_string($nameInput) ? trim($nameInput) : '';
            $category = is_string($categoryInput) ? trim($categoryInput) : '';
            $price = is_string($priceInput) ? filter_var($priceInput, FILTER_VALIDATE_INT) : false;
            $oldPriceInput = is_string($oldPriceValue) ? trim($oldPriceValue) : '';
            $oldPrice = $oldPriceInput === '' ? null : filter_var($oldPriceInput, FILTER_VALIDATE_INT);
            $image = is_string($imageInput) ? trim($imageInput) : '';
            $description = is_string($descriptionInput) ? trim($descriptionInput) : '';
            $stockQuantity = is_string($stockInput) ? filter_var($stockInput, FILTER_VALIDATE_INT) : false;
            $validImage = (str_starts_with($image, '/') && !str_starts_with($image, '//'))
                || (filter_var($image, FILTER_VALIDATE_URL) !== false && parse_url($image, PHP_URL_SCHEME) === 'https');

            if ($id === false) {
                $errors[] = 'পণ্য শনাক্তকরণ সঠিক নয়।';
            }
            if ($name === '' || text_length($name) > 120) {
                $errors[] = 'পণ্যের নাম ১২০ অক্ষরের মধ্যে লিখুন।';
            }
            if ($category === '' || text_length($category) > 60) {
                $errors[] = 'ক্যাটাগরি ৬০ অক্ষরের মধ্যে লিখুন।';
            }
            if ($price === false || $price < 0 || $price > 100000000) {
                $errors[] = 'পণ্যের মূল্য ০ থেকে ১০,০০,০০,০০০ টাকার মধ্যে হতে হবে।';
            }
            if ($oldPrice !== null && ($oldPrice === false || $oldPrice < 0 || $oldPrice > 100000000)) {
                $errors[] = 'পুরোনো মূল্য সঠিক নয়।';
            } elseif ($oldPrice !== null && $price !== false && $oldPrice <= $price) {
                $errors[] = 'আগের মূল্য বর্তমান মূল্যের চেয়ে বেশি হতে হবে।';
            }
            if ($stockQuantity === false || $stockQuantity < 0 || $stockQuantity > 1000000) {
                $errors[] = 'স্টক ০ থেকে ১০,০০,০০০-এর মধ্যে হতে হবে।';
            }
            if (!$validImage || text_length($image) > 500) {
                $errors[] = 'ছবির জন্য সাইটের ভেতরের path অথবা HTTPS URL দিন।';
            }
            if ($description === '' || text_length($description) > 2000) {
                $errors[] = 'পণ্যের বিবরণ ১ থেকে ২,০০০ অক্ষরের মধ্যে লিখুন।';
            }

            if ($errors === []) {
                $existing = null;
                if ($id !== null) {
                    $existingQuery = db()->prepare(
                        'SELECT id, status FROM seller_product_submissions WHERE id = :id AND seller_id = :seller_id'
                    );
                    $existingQuery->execute(['id' => $id, 'seller_id' => (int) $seller['id']]);
                    $existing = $existingQuery->fetch();
                    if ($existing === false) {
                        $errors[] = 'পণ্যটি পাওয়া যায়নি অথবা আপনার access নেই।';
                    } elseif ($existing['status'] === 'submitted') {
                        $errors[] = 'প্রশাসকের review চলাকালে পণ্যটি সম্পাদনা করা যাবে না।';
                    }
                }

                if ($errors === []) {
                    $status = $action === 'submit_product' ? 'submitted' : 'draft';
                    $parameters = [
                        'seller_id' => (int) $seller['id'],
                        'name' => $name,
                        'category' => $category,
                        'price' => $price,
                        'old_price' => $oldPrice,
                        'image' => $image,
                        'description' => $description,
                        'stock_quantity' => $stockQuantity,
                        'status' => $status,
                    ];
                    if ($id === null) {
                        $statement = db()->prepare(
                            'INSERT INTO seller_product_submissions
                             (seller_id, name, category, price, old_price, image, description, stock_quantity, status)
                             VALUES (:seller_id, :name, :category, :price, :old_price, :image, :description, :stock_quantity, :status)'
                        );
                    } else {
                        $statement = db()->prepare(
                            'UPDATE seller_product_submissions
                             SET name = :name, category = :category, price = :price, old_price = :old_price,
                                 image = :image, description = :description, stock_quantity = :stock_quantity,
                                 status = :status, admin_notes = "", reviewed_by = NULL, reviewed_at = NULL,
                                 updated_at = CURRENT_TIMESTAMP
                             WHERE id = :id AND seller_id = :seller_id AND status != "submitted"'
                        );
                        $parameters['id'] = $id;
                    }
                    $statement->execute($parameters);
                    if ($id !== null && $statement->rowCount() !== 1) {
                        $errors[] = 'পণ্যটি আপডেট করা যায়নি। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।';
                    } else {
                        flash('seller', $status === 'submitted'
                            ? 'পণ্য review-এর জন্য জমা হয়েছে। এটি এখনো storefront-এ দেখানো বা বিক্রি করা হবে না।'
                            : 'পণ্যের draft সংরক্ষণ হয়েছে।');
                        redirect('seller.php');
                    }
                }
            }
        }
    }
}

$editing = null;
$editingUnavailable = false;
if ($view === 'product') {
    $hasEditId = isset($_GET['id']);
    $editIdInput = $_GET['id'] ?? null;
    $editId = $hasEditId && (is_string($editIdInput) || is_int($editIdInput))
        ? filter_var($editIdInput, FILTER_VALIDATE_INT)
        : ($hasEditId ? false : null);
    if ($hasEditId && ($editId === false || $editId === null || $editId < 1)) {
        http_response_code(404);
        $editingUnavailable = true;
        $errors[] = 'পণ্যটি পাওয়া যায়নি অথবা আপনার access নেই।';
    } elseif ($editId !== null) {
        $statement = db()->prepare('SELECT * FROM seller_product_submissions WHERE id = :id AND seller_id = :seller_id');
        $statement->execute(['id' => $editId, 'seller_id' => (int) $seller['id']]);
        $editing = $statement->fetch() ?: null;
        if ($editing === null) {
            http_response_code(404);
            $editingUnavailable = true;
            $errors[] = 'পণ্যটি পাওয়া যায়নি অথবা আপনার access নেই।';
        }
    }
    if (($editing['status'] ?? null) === 'submitted') {
        $editingUnavailable = true;
        $errors[] = 'Review চলাকালে জমা দেওয়া পণ্য সম্পাদনা করা যাবে না।';
    }
}

$submissionsQuery = db()->prepare(
    'SELECT id, name, category, price, status, admin_notes, updated_at
     FROM seller_product_submissions WHERE seller_id = :seller_id ORDER BY id DESC'
);
$submissionsQuery->execute(['seller_id' => (int) $seller['id']]);
$submissions = $submissionsQuery->fetchAll();
$form = [
    'id' => $editing['id'] ?? '',
    'name' => $editing['name'] ?? '',
    'category' => $editing['category'] ?? '',
    'price' => $editing['price'] ?? '',
    'old_price' => $editing['old_price'] ?? '',
    'image' => $editing['image'] ?? '',
    'description' => $editing['description'] ?? '',
    'stock_quantity' => $editing['stock_quantity'] ?? 0,
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_string($_POST['action'] ?? null) && in_array($_POST['action'], ['save_product_draft', 'submit_product'], true)) {
    foreach ($form as $key => $_) {
        if (array_key_exists($key, $_POST)) {
            $form[$key] = is_scalar($_POST[$key]) ? (string) $_POST[$key] : '';
        }
    }
}

page_header('সেলার প্যানেল');
?>
<main class="seller-portal">
    <div class="admin-page-title"><div><span class="eyebrow">ALL SHOP MARKETPLACE</span><h1>সেলার প্যানেল</h1><p><?= e($seller['business_name']) ?> · <?= e($seller['email']) ?></p></div><form method="post" action="seller.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="button button-quiet" type="submit">লগআউট</button></form></div>
    <?php if ($notice !== null): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($errors !== []): ?><div class="form-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <p class="seller-stage-note">Stage 1: এখানে পণ্য draft ও catalog review করা যায়। Split order, seller checkout, commission settlement এবং payout এখনো চালু নয়; অনুমোদিত submission-ও ক্রেতাদের কাছে বিক্রি বা storefront-এ প্রকাশিত হবে না।</p>

    <?php if ($view === 'product' && $editingUnavailable): ?>
        <section class="admin-panel seller-product-editor"><h2>পণ্য সম্পাদনা করা যাচ্ছে না</h2><p>Review চলমান থাকতে পারে, অথবা পণ্যটি আপনার account-এর নয়।</p><a class="table-link" href="seller.php">← আপনার পণ্যের তালিকা</a></section>
    <?php elseif ($view === 'product'): ?>
        <section class="admin-panel seller-product-editor">
            <div class="admin-panel-heading"><h2><?= $editing === null ? 'নতুন পণ্যের draft' : 'পণ্য সম্পাদনা' ?></h2><a href="seller.php">← তালিকায় ফিরুন</a></div>
            <form class="seller-product-form" method="post" action="seller.php?view=product<?= $form['id'] !== '' ? '&id=' . (int) $form['id'] : '' ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">
                <label>পণ্যের নাম<input name="name" maxlength="120" value="<?= e((string) $form['name']) ?>" required></label>
                <label>ক্যাটাগরি<input name="category" maxlength="60" value="<?= e((string) $form['category']) ?>" required></label>
                <label>মূল্য (৳)<input type="number" min="0" max="100000000" name="price" value="<?= e((string) $form['price']) ?>" required></label>
                <label>আগের মূল্য (ঐচ্ছিক)<input type="number" min="0" max="100000000" name="old_price" value="<?= e((string) $form['old_price']) ?>"></label>
                <label>ছবির path বা HTTPS URL<input name="image" maxlength="500" value="<?= e((string) $form['image']) ?>" placeholder="/images/product.jpg অথবা https://..." required></label>
                <label>প্রস্তাবিত stock<input type="number" min="0" max="1000000" name="stock_quantity" value="<?= e((string) $form['stock_quantity']) ?>" required></label>
                <label class="seller-form-wide">পণ্যের বিবরণ<textarea name="description" rows="5" maxlength="2000" required><?= e((string) $form['description']) ?></textarea></label>
                <?php if ($editing !== null && $editing['admin_notes'] !== ''): ?><p class="seller-form-wide seller-admin-note">আগের review note: <?= e($editing['admin_notes']) ?></p><?php endif; ?>
                <div class="seller-form-actions"><button class="button button-quiet" type="submit" name="action" value="save_product_draft">Draft সংরক্ষণ</button><button class="button button-dark" type="submit" name="action" value="submit_product">Review-এর জন্য জমা দিন</button></div>
            </form>
        </section>
    <?php else: ?>
        <div class="admin-page-title seller-list-heading"><div><h2>আপনার পণ্য submission</h2><p><?= count($submissions) ?>টি পণ্য</p></div><a class="button button-dark" href="seller.php?view=product">+ পণ্য draft</a></div>
        <?php if ($submissions === []): ?><section class="admin-panel admin-empty">এখনো কোনো পণ্য draft নেই।</section><?php else: ?>
            <section class="admin-panel"><div class="table-wrap"><table><thead><tr><th>পণ্য</th><th>ক্যাটাগরি</th><th>মূল্য</th><th>অবস্থা</th><th>আপডেট</th><th></th></tr></thead><tbody>
                <?php foreach ($submissions as $submission): ?>
                    <tr><td><strong><?= e($submission['name']) ?></strong><?php if ($submission['admin_notes'] !== ''): ?><small class="table-sub"><?= e($submission['admin_notes']) ?></small><?php endif; ?></td><td><?= e($submission['category']) ?></td><td><?= taka((int) $submission['price']) ?></td><td><span class="status-chip"><?= e(match ($submission['status']) { 'draft' => 'Draft', 'submitted' => 'Review-এ', 'approved' => 'Catalog review অনুমোদিত', default => 'পুনরায় সম্পাদনা প্রয়োজন' }) ?></span></td><td><?= e($submission['updated_at']) ?></td><td><?php if ($submission['status'] !== 'submitted'): ?><a class="table-link" href="seller.php?view=product&amp;id=<?= (int) $submission['id'] ?>">সম্পাদনা</a><?php else: ?>Review চলছে<?php endif; ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div></section>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php page_footer(); ?>
