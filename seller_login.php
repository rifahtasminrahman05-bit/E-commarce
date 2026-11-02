<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

if (current_seller() !== null) {
    redirect('seller.php');
}

$errors = [];
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';
    if (!verify_csrf()) {
        $errors[] = 'সেশন যাচাই করা যায়নি। আবার চেষ্টা করুন।';
    } elseif (seller_login_is_blocked($email)) {
        $errors[] = 'নিরাপত্তার জন্য লগইন সাময়িকভাবে বন্ধ আছে। ১৫ মিনিট পর আবার চেষ্টা করুন।';
    } else {
        $statement = db()->prepare('SELECT id, password_hash, status FROM sellers WHERE email = :email');
        $statement->execute(['email' => $email]);
        $seller = $statement->fetch();
        if ($seller !== false && password_verify($password, $seller['password_hash']) && $seller['status'] === 'active') {
            clear_seller_login_failures($email);
            session_regenerate_id(true);
            unset($_SESSION['admin_user'], $_SESSION['admin_last_activity']);
            $_SESSION['seller_id'] = (int) $seller['id'];
            $_SESSION['seller_last_activity'] = time();
            db()->prepare('UPDATE sellers SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id')
                ->execute(['id' => (int) $seller['id']]);
            redirect('seller.php');
        }

        $applicationStatement = db()->prepare('SELECT password_hash, status FROM seller_applications WHERE email = :email');
        $applicationStatement->execute(['email' => $email]);
        $application = $applicationStatement->fetch();
        if (
            $seller === false
            && $application !== false
            && password_verify($password, $application['password_hash'])
            && in_array($application['status'], ['pending', 'rejected'], true)
        ) {
            $errors[] = $application['status'] === 'pending'
                ? 'আপনার আবেদন এখনো যাচাই হচ্ছে। অনুমোদনের পর এই ইমেইল ও পাসওয়ার্ড দিয়ে লগইন করতে পারবেন।'
                : 'আপনার আবেদন অনুমোদিত হয়নি। বিস্তারিত জানতে প্রশাসকের সঙ্গে যোগাযোগ করুন।';
        } else {
            record_seller_login_failure($email);
            usleep(300000);
            $errors[] = 'ইমেইল অথবা পাসওয়ার্ড সঠিক নয়, অথবা account সক্রিয় নয়।';
        }
    }
}

page_header('সেলার লগইন');
?>
<main class="seller-auth-page">
    <section class="admin-login-card seller-auth-card">
        <span class="eyebrow">ALL SHOP MARKETPLACE</span>
        <h1>সেলার লগইন</h1>
        <p>অনুমোদিত সেলাররা তাদের পণ্যের draft ও review status পরিচালনা করতে পারবেন।</p>
        <?php if ($errors !== []): ?><div class="form-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="seller_login.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>ইমেইল<input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" maxlength="190" required></label>
            <label>পাসওয়ার্ড<input type="password" name="password" autocomplete="current-password" maxlength="128" required></label>
            <button class="button button-dark button-full" type="submit">লগইন <span>↗</span></button>
        </form>
        <a class="admin-back-link" href="seller_apply.php">নতুন সেলার আবেদন</a>
        <a class="admin-back-link" href="index.php">← দোকানে ফিরে যান</a>
    </section>
</main>
<?php page_footer(); ?>
