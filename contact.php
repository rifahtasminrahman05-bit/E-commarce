<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

$errors = [];
$notice = flash('contact');
$form = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'subject' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $field => $_) {
        $form[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if (!verify_csrf()) {
        $errors[] = 'অনুরোধটি যাচাই করা যায়নি। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।';
    } else {
        if ($form['name'] === '' || text_length($form['name']) > 100) {
            $errors[] = 'আপনার নাম ১ থেকে ১০০ অক্ষরের মধ্যে লিখুন।';
        }
        if (filter_var($form['email'], FILTER_VALIDATE_EMAIL) === false || strlen($form['email']) > 254) {
            $errors[] = 'সঠিক ইমেইল ঠিকানা লিখুন।';
        }
        if (text_length($form['phone']) > 30) {
            $errors[] = 'ফোন নম্বর ৩০ অক্ষরের মধ্যে লিখুন।';
        }
        if ($form['subject'] === '' || text_length($form['subject']) > 120) {
            $errors[] = 'বিষয় ১ থেকে ১২০ অক্ষরের মধ্যে লিখুন।';
        }
        if ($form['message'] === '' || text_length($form['message']) > 3000) {
            $errors[] = 'বার্তা ১ থেকে ৩,০০০ অক্ষরের মধ্যে লিখুন।';
        }

        if ($errors === []) {
            $statement = db()->prepare(
                'INSERT INTO contact_messages (name, email, phone, subject, message)
                 VALUES (:name, :email, :phone, :subject, :message)'
            );
            $statement->execute($form);
            flash('contact', 'আপনার বার্তা আমাদের কাছে পৌঁছেছে। ধন্যবাদ!');
            redirect('contact.php');
        }
    }
}

page_header('যোগাযোগ');
?>
<main class="inner-page contact-page">
    <div class="breadcrumb"><a href="index.php">হোম</a><span>/</span><span>যোগাযোগ</span></div>
    <div class="inner-heading"><div><span class="eyebrow">আমরা সাহায্য করতে প্রস্তুত</span><h1>কীভাবে <em>সহায়তা করতে পারি?</em></h1></div></div>
    <?php if ($notice !== null): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($errors !== []): ?><div class="form-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="contact-layout">
        <section class="contact-intro">
            <span class="contact-icon" aria-hidden="true">✉</span>
            <h2>আপনার প্রশ্নটি আমাদের জানান</h2>
            <p>অর্ডার, পণ্য বা কেনাকাটা নিয়ে কোনো প্রশ্ন থাকলে ফর্মটি পূরণ করুন। অর্ডার-সংক্রান্ত বিষয়ে অর্ডার নম্বর ও checkout-এ দেওয়া ফোন নম্বর উল্লেখ করলে আমরা দ্রুত খুঁজে পেতে পারব।</p>
            <a class="contact-help-link" href="help.php">সাধারণ প্রশ্নের উত্তর দেখুন <span>↗</span></a>
        </section>
        <form class="checkout-panel contact-form" method="post" action="contact.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>আপনার নাম<input name="name" maxlength="100" autocomplete="name" required value="<?= e($form['name']) ?>"></label>
            <label>ইমেইল ঠিকানা<input type="email" name="email" maxlength="254" autocomplete="email" required value="<?= e($form['email']) ?>"></label>
            <label>ফোন নম্বর <span>(ঐচ্ছিক)</span><input type="tel" name="phone" maxlength="30" autocomplete="tel" value="<?= e($form['phone']) ?>"></label>
            <label>বিষয়<input name="subject" maxlength="120" required value="<?= e($form['subject']) ?>"></label>
            <label>আপনার বার্তা<textarea name="message" rows="6" maxlength="3000" required><?= e($form['message']) ?></textarea></label>
            <p class="contact-privacy">বার্তার উত্তর দেওয়ার জন্য আপনার দেওয়া তথ্য ব্যবহার করা হবে।</p>
            <button class="button button-dark" type="submit">বার্তা পাঠান <span>↗</span></button>
        </form>
    </div>
</main>
<?php page_footer(); ?>
