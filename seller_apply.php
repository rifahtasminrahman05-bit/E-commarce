<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

if (current_seller() !== null) {
    redirect('seller.php');
}

$errors = [];
$notice = flash('seller');
$values = [
    'business_name' => '',
    'owner_name' => '',
    'email' => '',
    'phone' => '',
    'business_address' => '',
    'categories' => '',
    'website' => '',
    'application_note' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $input = $_POST[$key] ?? '';
        $values[$key] = is_string($input) ? trim($input) : '';
    }
    $passwordInput = $_POST['password'] ?? '';
    $password = is_string($passwordInput) ? $passwordInput : '';
    $email = strtolower($values['email']);
    $values['email'] = $email;

    if (!verify_csrf()) {
        $errors[] = 'সেশন যাচাই করা যায়নি। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।';
    }
    if ($values['business_name'] === '' || text_length($values['business_name']) > 120) {
        $errors[] = 'ব্যবসার নাম ১২০ অক্ষরের মধ্যে লিখুন।';
    }
    if ($values['owner_name'] === '' || text_length($values['owner_name']) > 120) {
        $errors[] = 'মালিক/যোগাযোগকারীর নাম ১২০ অক্ষরের মধ্যে লিখুন।';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'একটি সঠিক ইমেইল ঠিকানা লিখুন।';
    }
    if (!preg_match('/^[0-9+().\-\s]{7,24}$/', $values['phone'])) {
        $errors[] = 'সঠিক ফোন নম্বর লিখুন।';
    }
    if ($values['business_address'] === '' || text_length($values['business_address']) > 250) {
        $errors[] = 'ব্যবসার ঠিকানা ২৫০ অক্ষরের মধ্যে লিখুন।';
    }
    if ($values['categories'] === '' || text_length($values['categories']) > 120) {
        $errors[] = 'আপনার পণ্যের ক্যাটাগরি ১২০ অক্ষরের মধ্যে লিখুন।';
    }
    if ($values['website'] !== '') {
        $website = parse_url($values['website']);
        if (
            filter_var($values['website'], FILTER_VALIDATE_URL) === false
            || !is_array($website)
            || ($website['scheme'] ?? null) !== 'https'
        ) {
            $errors[] = 'ওয়েবসাইট দিলে একটি HTTPS ঠিকানা ব্যবহার করুন।';
        }
    }
    if (text_length($values['application_note']) > 500) {
        $errors[] = 'অতিরিক্ত তথ্য ৫০০ অক্ষরের মধ্যে লিখুন।';
    }
    if (strlen($password) < 12 || strlen($password) > 128) {
        $errors[] = 'পাসওয়ার্ড ১২ থেকে ১২৮ অক্ষরের মধ্যে লিখুন।';
    }

    if ($errors === [] && seller_application_is_rate_limited()) {
        $errors[] = 'এই নেটওয়ার্ক থেকে প্রতি ঘণ্টায় সর্বোচ্চ ৩টি আবেদন জমা দেওয়া যাবে। পরে আবার চেষ্টা করুন।';
    }

    if ($errors === []) {
        $sellerExists = db()->prepare('SELECT 1 FROM sellers WHERE email = :email');
        $sellerExists->execute(['email' => $email]);
        $existingApplication = db()->prepare('SELECT id, status FROM seller_applications WHERE email = :email');
        $existingApplication->execute(['email' => $email]);
        $application = $existingApplication->fetch();
        if ($sellerExists->fetchColumn() !== false) {
            $errors[] = 'এই ইমেইলে seller account আগে থেকেই আছে। সেলার লগইন করুন।';
        } elseif ($application !== false && $application['status'] !== 'rejected') {
            $errors[] = 'এই ইমেইলে একটি আবেদন আগে থেকেই আছে। সিদ্ধান্তের জন্য অপেক্ষা করুন অথবা প্রশাসকের সঙ্গে যোগাযোগ করুন।';
        } else {
            $applicationData = [
                'business_name' => $values['business_name'],
                'owner_name' => $values['owner_name'],
                'email' => $email,
                'phone' => $values['phone'],
                'business_address' => $values['business_address'],
                'categories' => $values['categories'],
                'website' => $values['website'],
                'application_note' => $values['application_note'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ];
            if ($application !== false) {
                $statement = db()->prepare(
                    'UPDATE seller_applications
                     SET business_name = :business_name, owner_name = :owner_name, phone = :phone,
                         business_address = :business_address, categories = :categories, website = :website,
                         application_note = :application_note, password_hash = :password_hash,
                         status = "pending", business_verified = 0, phone_verified = 0,
                         payout_verified = 0, legal_tax_verified = 0, admin_notes = "",
                         reviewed_by = NULL, reviewed_at = NULL
                     WHERE id = :id AND status = "rejected"'
                );
                $statement->execute([...$applicationData, 'id' => (int) $application['id']]);
                if ($statement->rowCount() !== 1) {
                    $errors[] = 'আবেদনটি পরিবর্তিত হয়েছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।';
                }
            } else {
                $statement = db()->prepare(
                    'INSERT INTO seller_applications
                     (business_name, owner_name, email, phone, business_address, categories, website, application_note, password_hash)
                     VALUES (:business_name, :owner_name, :email, :phone, :business_address, :categories, :website, :application_note, :password_hash)'
                );
                $statement->execute($applicationData);
            }
            if ($errors === []) {
                record_seller_application_attempt();
                flash('seller', 'আপনার আবেদন জমা হয়েছে। ব্যবসা, ফোন, payout account ownership এবং প্রয়োজনীয় legal/tax তথ্য যাচাইয়ের পর সিদ্ধান্ত জানানো হবে।');
                redirect('seller_apply.php');
            }
        }
    }
}

page_header('সেলার আবেদন');
?>
<main class="seller-auth-page">
    <section class="admin-login-card seller-auth-card">
        <span class="eyebrow">ALL SHOP MARKETPLACE</span>
        <h1>সেলার হিসেবে আবেদন</h1>
        <p>আবেদন অনুমোদনের আগে কোনো seller account বা পণ্য ক্রেতাদের কাছে বিক্রি হবে না।</p>
        <?php if ($notice !== null): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>
        <?php if ($errors !== []): ?><div class="form-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="seller_apply.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>ব্যবসার নাম<input name="business_name" maxlength="120" value="<?= e($values['business_name']) ?>" autocomplete="organization" required></label>
            <label>মালিক/যোগাযোগকারীর নাম<input name="owner_name" maxlength="120" value="<?= e($values['owner_name']) ?>" autocomplete="name" required></label>
            <label>ইমেইল<input type="email" name="email" maxlength="190" value="<?= e($values['email']) ?>" autocomplete="email" required></label>
            <label>ফোন নম্বর<input type="tel" name="phone" maxlength="24" value="<?= e($values['phone']) ?>" autocomplete="tel" required></label>
            <label>ব্যবসার ঠিকানা<input name="business_address" maxlength="250" value="<?= e($values['business_address']) ?>" autocomplete="street-address" required></label>
            <label>পণ্যের ক্যাটাগরি<input name="categories" maxlength="120" value="<?= e($values['categories']) ?>" placeholder="যেমন: Fashion, Home" required></label>
            <label>ওয়েবসাইট (ঐচ্ছিক)<input type="url" name="website" maxlength="255" value="<?= e($values['website']) ?>" placeholder="https://"></label>
            <label>অতিরিক্ত তথ্য (ঐচ্ছিক)<textarea name="application_note" maxlength="500" rows="3"><?= e($values['application_note']) ?></textarea></label>
            <label>পাসওয়ার্ড<input type="password" name="password" minlength="12" maxlength="128" autocomplete="new-password" required><small>কমপক্ষে ১২ অক্ষর।</small></label>
            <p class="seller-stage-note">পরিচয়পত্র, payout credentials বা legal/tax নথি এই ফর্মে আপলোড করবেন না। প্রশাসক আলাদাভাবে যাচাই করবেন; এখনো কোনো পেমেন্ট বা ডেলিভারি provider সংযুক্ত নয়।</p>
            <button class="button button-dark button-full" type="submit">আবেদন জমা দিন <span>↗</span></button>
        </form>
        <a class="admin-back-link" href="seller_login.php">আগে থেকেই সেলার? লগইন করুন</a>
        <a class="admin-back-link" href="index.php">← দোকানে ফিরে যান</a>
    </section>
</main>
<?php page_footer(); ?>
