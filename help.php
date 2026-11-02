<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

page_header('সাহায্য ও সাধারণ জিজ্ঞাসা');
?>
<main class="inner-page help-page">
    <div class="breadcrumb"><a href="index.php">হোম</a><span>/</span><span>সাহায্য</span></div>
    <div class="inner-heading"><div><span class="eyebrow">কেনাকাটা আরও সহজ হোক</span><h1>সাধারণ <em>জিজ্ঞাসা</em></h1></div></div>
    <section class="faq-list" aria-label="সাধারণ প্রশ্ন ও উত্তর">
        <details class="faq-item">
            <summary>কীভাবে অর্ডার করব?</summary>
            <p>পছন্দের পণ্য কার্টে যোগ করে checkout-এ নাম, ফোন নম্বর ও ডেলিভারি ঠিকানা দিন। অর্ডার নিশ্চিত করার আগে পেমেন্ট পদ্ধতি ও মোট খরচ দেখে নিন।</p>
        </details>
        <details class="faq-item">
            <summary>কোন কোন পেমেন্ট পদ্ধতি আছে?</summary>
            <p>Checkout-এ ক্যাশ অন ডেলিভারি ও bKash দেখানো হয়। bKash checkout চালু থাকতে merchant configuration প্রয়োজন; কোনো পদ্ধতি কাজ না করলে ক্যাশ অন ডেলিভারি বেছে নিন বা আমাদের জানান।</p>
        </details>
        <details class="faq-item">
            <summary>ডেলিভারি চার্জ কত এবং কখন পণ্য পাব?</summary>
            <p>বর্তমান checkout-এ ৳১,৫০০-এর কম অর্ডারে ডেলিভারি ৳৮০ এবং ৳১,৫০০ বা তার বেশি অর্ডারে বিনামূল্যে। সাধারণত ২–৫ কর্মদিবসের মধ্যে পৌঁছানোর লক্ষ্য রাখা হয়। অর্ডার নিশ্চিত করার আগে checkout-এ দেখানো চার্জই চূড়ান্ত।</p>
        </details>
        <details class="faq-item">
            <summary>আমার অর্ডারের অবস্থা কোথায় দেখব?</summary>
            <p>অর্ডার নিশ্চিতকরণের সময় পাওয়া অর্ডার নম্বর এবং checkout-এ দেওয়া ফোন নম্বর দিয়ে <a href="order.php">অর্ডার ট্র্যাক</a> পেজে খুঁজুন।</p>
        </details>
        <details class="faq-item">
            <summary>পণ্য স্টকে আছে কি না কীভাবে বুঝব?</summary>
            <p>পণ্যের পেজ ও কার্টে স্টকের তথ্য দেখানো হয়। স্টক অর্ডার নিশ্চিত করার সময় আবার যাচাই হয়; এর মধ্যে স্টক বদলে গেলে checkout-এ বার্তা দেখানো হবে।</p>
        </details>
        <details class="faq-item">
            <summary>কুপন কোথায় ব্যবহার করব?</summary>
            <p>কার্টের অর্ডার সারাংশে কুপন কোড লিখে প্রয়োগ করুন। কুপনের শর্ত পূরণ না হলে কার্টে কারণ জানানো হবে।</p>
        </details>
        <details class="faq-item">
            <summary>পণ্য বা অর্ডার নিয়ে আরও সাহায্য লাগলে কী করব?</summary>
            <p><a href="contact.php">যোগাযোগ ফর্মে</a> আপনার প্রশ্ন পাঠান। অর্ডার-সংক্রান্ত প্রশ্নে অর্ডার নম্বর ও checkout-এর ফোন নম্বর দিলে সহায়তা করা সহজ হবে।</p>
        </details>
    </section>
    <section class="faq-contact">
        <div><span class="eyebrow">এখানে উত্তর পেলেন না?</span><h2>আমাদের সাথে কথা বলুন</h2><p>আপনার প্রশ্নটি পাঠান—আমরা প্রয়োজনীয় তথ্যসহ সাহায্য করব।</p></div>
        <a class="button button-dark" href="contact.php">যোগাযোগ করুন <span>↗</span></a>
    </section>
</main>
<?php page_footer(); ?>
