<?php
declare(strict_types=1);
require __DIR__ . '/functions.php';

$view = (string) ($_GET['view'] ?? 'dashboard');
$allowedViews = ['login', 'dashboard', 'products', 'product', 'orders', 'order', 'subscribers', 'messages', 'coupons', 'reviews', 'search_analytics', 'seller_applications', 'seller_products'];
if (!in_array($view, $allowedViews, true)) {
    http_response_code(404);
    $view = 'dashboard';
}

$errors = [];
$notice = flash('admin');
if ($view === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!verify_csrf()) {
        $errors[] = 'সেশন যাচাই করা যায়নি। আবার চেষ্টা করুন।';
    } elseif (admin_login_is_blocked($username)) {
        $errors[] = 'নিরাপত্তার জন্য লগইন সাময়িকভাবে বন্ধ আছে। ১৫ মিনিট পর আবার চেষ্টা করুন।';
    } else {
        $statement = db()->prepare('SELECT username, password_hash FROM admin_users WHERE username = :username');
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            clear_admin_login_failures($username);
            session_regenerate_id(true);
            unset($_SESSION['seller_id'], $_SESSION['seller_last_activity']);
            $_SESSION['admin_user'] = $user['username'];
            $_SESSION['admin_last_activity'] = time();
            redirect('admin.php');
        }
        record_admin_login_failure($username);
        usleep(300000);
        $errors[] = 'ইউজারনেম অথবা পাসওয়ার্ড সঠিক নয়।';
    }
}

if ($view !== 'login') {
    require_admin();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $view !== 'login') {
    if (!verify_csrf()) {
        $errors[] = 'সেশন যাচাই করা যায়নি। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।';
    } else {
        $actionInput = $_POST['action'] ?? '';
        $action = is_string($actionInput) ? $actionInput : '';
        if ($action === 'logout') {
            unset($_SESSION['admin_user'], $_SESSION['admin_last_activity']);
            session_regenerate_id(true);
            redirect('admin.php?view=login');
        }
        if ($action === 'review_seller_application') {
            $idInput = $_POST['id'] ?? null;
            $decisionInput = $_POST['decision'] ?? '';
            $notesInput = $_POST['admin_notes'] ?? '';
            $id = is_string($idInput) || is_int($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
            $decision = is_string($decisionInput) ? $decisionInput : '';
            $verification = [
                'business_verified' => ($_POST['business_verified'] ?? null) === '1' ? 1 : 0,
                'phone_verified' => ($_POST['phone_verified'] ?? null) === '1' ? 1 : 0,
                'payout_verified' => ($_POST['payout_verified'] ?? null) === '1' ? 1 : 0,
                'legal_tax_verified' => ($_POST['legal_tax_verified'] ?? null) === '1' ? 1 : 0,
            ];
            $adminNotes = is_string($notesInput) ? trim($notesInput) : '';
            if ($id === false || $id === null || !in_array($decision, ['approved', 'rejected'], true) || text_length($adminNotes) > 1000) {
                $errors[] = 'সেলার আবেদন পর্যালোচনার তথ্য সঠিক নয়।';
            } elseif ($decision === 'approved' && in_array(0, $verification, true)) {
                $errors[] = 'অনুমোদনের আগে ব্যবসা, ফোন, payout ownership এবং প্রযোজ্য legal/tax তথ্য যাচাই করুন।';
            } else {
                $pdo = db();
                $applicationQuery = $pdo->prepare(
                    'SELECT id, business_name, owner_name, email, phone, business_address, categories, website, password_hash
                     FROM seller_applications WHERE id = :id AND status = "pending"'
                );
                $applicationQuery->execute(['id' => $id]);
                $application = $applicationQuery->fetch();
                if ($application === false) {
                    $errors[] = 'আবেদনটি পাওয়া যায়নি অথবা ইতিমধ্যে পর্যালোচনা করা হয়েছে।';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $update = $pdo->prepare(
                            'UPDATE seller_applications
                             SET status = :status, business_verified = :business_verified,
                                 phone_verified = :phone_verified, payout_verified = :payout_verified,
                                 legal_tax_verified = :legal_tax_verified, admin_notes = :admin_notes,
                                 reviewed_by = :reviewed_by, reviewed_at = CURRENT_TIMESTAMP
                             WHERE id = :id AND status = "pending"'
                        );
                        $update->execute([
                            'status' => $decision,
                            ...$verification,
                            'admin_notes' => $adminNotes,
                            'reviewed_by' => (string) $_SESSION['admin_user'],
                            'id' => $id,
                        ]);
                        if ($update->rowCount() !== 1) {
                            throw new RuntimeException('Seller application was already reviewed.');
                        }
                        if ($decision === 'approved') {
                            $createSeller = $pdo->prepare(
                                'INSERT INTO sellers
                                 (application_id, business_name, owner_name, email, phone, business_address, categories, website, password_hash)
                                 VALUES (:application_id, :business_name, :owner_name, :email, :phone, :business_address, :categories, :website, :password_hash)'
                            );
                            $createSeller->execute([
                                'application_id' => (int) $application['id'],
                                'business_name' => $application['business_name'],
                                'owner_name' => $application['owner_name'],
                                'email' => $application['email'],
                                'phone' => $application['phone'],
                                'business_address' => $application['business_address'],
                                'categories' => $application['categories'],
                                'website' => $application['website'],
                                'password_hash' => $application['password_hash'],
                            ]);
                            $pdo->prepare('UPDATE seller_applications SET password_hash = "" WHERE id = :id')
                                ->execute(['id' => $id]);
                        }
                        $pdo->commit();
                    } catch (Throwable $error) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $error;
                    }
                    flash('admin', $decision === 'approved' ? 'সেলার যাচাই সম্পন্ন; seller account তৈরি হয়েছে।' : 'সেলার আবেদন প্রত্যাখ্যান করা হয়েছে।');
                    redirect('admin.php?view=seller_applications');
                }
            }
        } elseif ($action === 'review_seller_product') {
            $idInput = $_POST['id'] ?? null;
            $decisionInput = $_POST['decision'] ?? '';
            $notesInput = $_POST['admin_notes'] ?? '';
            $id = is_string($idInput) || is_int($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
            $decision = is_string($decisionInput) ? $decisionInput : '';
            $adminNotes = is_string($notesInput) ? trim($notesInput) : '';
            if ($id === false || $id === null || !in_array($decision, ['approved', 'rejected'], true) || text_length($adminNotes) > 1000) {
                $errors[] = 'সেলার পণ্যের পর্যালোচনা তথ্য সঠিক নয়।';
            } else {
                $statement = db()->prepare(
                    'UPDATE seller_product_submissions
                     SET status = :status, admin_notes = :admin_notes, reviewed_by = :reviewed_by,
                         reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id AND status = "submitted"'
                );
                $statement->execute([
                    'status' => $decision,
                    'admin_notes' => $adminNotes,
                    'reviewed_by' => (string) $_SESSION['admin_user'],
                    'id' => $id,
                ]);
                if ($statement->rowCount() !== 1) {
                    $errors[] = 'পণ্যটি পাওয়া যায়নি অথবা ইতিমধ্যে পর্যালোচনা করা হয়েছে।';
                } else {
                    flash('admin', $decision === 'approved'
                        ? 'পণ্যটি marketplace catalog review-তে অনুমোদিত হয়েছে; এখনো ক্রেতাদের কাছে বিক্রি বা প্রদর্শিত হবে না।'
                        : 'সেলার পণ্যটি প্রত্যাখ্যান করা হয়েছে।');
                    redirect('admin.php?view=seller_products');
                }
            }
        } elseif ($action === 'save_product') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string) ($_POST['name'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_INT);
            $oldPriceInput = trim((string) ($_POST['old_price'] ?? ''));
            $oldPrice = $oldPriceInput === '' ? null : filter_var($oldPriceInput, FILTER_VALIDATE_INT);
            $badge = trim((string) ($_POST['badge'] ?? ''));
            $image = trim((string) ($_POST['image'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $searchTermsInput = trim((string) ($_POST['search_terms'] ?? ''));
            $searchTerms = [];
            $seenSearchTerms = [];
            $rawSearchTerms = array_values(array_filter(
                preg_split('/[,;\r\n]+/u', $searchTermsInput) ?: [],
                static fn(string $term): bool => trim($term) !== ''
            ));
            if (count($rawSearchTerms) > 30) {
                $errors[] = 'একটি পণ্যে সর্বোচ্চ ৩০টি সার্চ শব্দ যোগ করা যাবে।';
            } else {
                foreach ($rawSearchTerms as $term) {
                    $term = trim($term);
                    $normalizedTerm = normalize_search_text($term);
                    if ($normalizedTerm === '' || text_length($term) > 60) {
                        $errors[] = 'প্রতিটি সার্চ শব্দ ১–৬০ অক্ষরের মধ্যে লিখুন।';
                        break;
                    }
                    if (isset($seenSearchTerms[$normalizedTerm])) {
                        $errors[] = 'একই সার্চ শব্দ একাধিকবার দেওয়া যাবে না।';
                        break;
                    }
                    $seenSearchTerms[$normalizedTerm] = true;
                    $searchTerms[] = $term;
                }
            }
            $trackInventory = isset($_POST['track_inventory']) ? 1 : 0;
            $stockQuantity = filter_var($_POST['stock_quantity'] ?? 0, FILTER_VALIDATE_INT);
            $lowStockThreshold = filter_var($_POST['low_stock_threshold'] ?? 5, FILTER_VALIDATE_INT);
            $variantsInput = trim((string) ($_POST['variants'] ?? ''));
            $variants = [];
            $seenVariants = [];
            $validImage = (str_starts_with($image, '/') && !str_starts_with($image, '//'))
                || (filter_var($image, FILTER_VALIDATE_URL) !== false && parse_url($image, PHP_URL_SCHEME) === 'https');

            if ($name === '' || text_length($name) > 120) {
                $errors[] = 'পণ্যের নাম ১২০ অক্ষরের মধ্যে লিখুন।';
            }
            if ($category === '' || text_length($category) > 60) {
                $errors[] = 'ক্যাটাগরি ৬০ অক্ষরের মধ্যে লিখুন।';
            }
            if ($price === false || $price < 0 || $price > 100000000) {
                $errors[] = 'সঠিক পণ্যমূল্য লিখুন।';
            }
            if ($oldPrice !== null && ($oldPrice === false || $oldPrice < 0 || $oldPrice > 100000000)) {
                $errors[] = 'পুরোনো মূল্য সঠিক নয়।';
            } elseif ($oldPrice !== null && $price !== false && $oldPrice <= $price) {
                $errors[] = 'আগের মূল্য বর্তমান মূল্যের চেয়ে বেশি হতে হবে।';
            }
            if ($variantsInput === '' && ($stockQuantity === false || $stockQuantity < 0 || $stockQuantity > 1000000)) {
                $errors[] = 'স্টকের পরিমাণ ০ থেকে ১০,০০,০০০-এর মধ্যে হতে হবে।';
            }
            if ($lowStockThreshold === false || $lowStockThreshold < 0 || $lowStockThreshold > 1000000) {
                $errors[] = 'কম-স্টক সতর্কতার সীমা সঠিক নয়।';
            }
            if ($variantsInput !== '') {
                $lines = array_values(array_filter(
                    preg_split('/\R/u', $variantsInput) ?: [],
                    static fn(string $line): bool => trim($line) !== ''
                ));
                if (count($lines) > 50) {
                    $errors[] = 'একটি পণ্যে সর্বোচ্চ ৫০টি ভ্যারিয়েন্ট রাখা যাবে।';
                } else {
                    foreach ($lines as $line) {
                        $parts = explode('|', $line, 2);
                        $label = trim($parts[0] ?? '');
                        $variantStock = filter_var(trim($parts[1] ?? ''), FILTER_VALIDATE_INT);
                        if ($label === '' || text_length($label) > 100 || count($parts) !== 2 || $variantStock === false || $variantStock < 0 || $variantStock > 1000000) {
                            $errors[] = 'প্রতি ভ্যারিয়েন্টে “নাম | স্টক” লিখুন; নাম ১০০ অক্ষর ও স্টক ০–১০,০০,০০০-এর মধ্যে হতে হবে।';
                            break;
                        }
                        $normalizedLabel = strtolower($label);
                        if (isset($seenVariants[$normalizedLabel])) {
                            $errors[] = 'একই ভ্যারিয়েন্টের নাম একাধিকবার দেওয়া যাবে না।';
                            break;
                        }
                        $seenVariants[$normalizedLabel] = true;
                        $variants[$label] = $variantStock;
                    }
                }
                $trackInventory = 1;
            }
            if (text_length($badge) > 30 || !$validImage || text_length($description) > 2000) {
                $errors[] = 'ব্যাজ, ছবি অথবা বিবরণের তথ্য সঠিক নয়। HTTPS ছবির লিংক ব্যবহার করুন।';
            }

            if ($errors === []) {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $productStock = $variants === [] ? $stockQuantity : array_sum($variants);
                    if ($id !== false && $id !== null) {
                        $statement = $pdo->prepare(
                            'UPDATE products SET name = :name, category = :category, price = :price, old_price = :old_price,
                             badge = :badge, image = :image, description = :description, track_inventory = :track_inventory,
                             stock_quantity = :stock_quantity, low_stock_threshold = :threshold WHERE id = :id'
                        );
                        $statement->execute([
                            'name' => $name, 'category' => $category, 'price' => $price, 'old_price' => $oldPrice,
                            'badge' => $badge, 'image' => $image, 'description' => $description,
                            'track_inventory' => $trackInventory, 'stock_quantity' => $productStock,
                            'threshold' => $lowStockThreshold, 'id' => $id,
                        ]);
                        $productId = (int) $id;
                    } else {
                        $statement = $pdo->prepare(
                            'INSERT INTO products (name, category, price, old_price, badge, image, description, track_inventory, stock_quantity, low_stock_threshold)
                             VALUES (:name, :category, :price, :old_price, :badge, :image, :description, :track_inventory, :stock_quantity, :threshold)'
                        );
                        $statement->execute([
                            'name' => $name, 'category' => $category, 'price' => $price, 'old_price' => $oldPrice,
                            'badge' => $badge, 'image' => $image, 'description' => $description,
                            'track_inventory' => $trackInventory, 'stock_quantity' => $productStock,
                            'threshold' => $lowStockThreshold,
                        ]);
                        $productId = (int) $pdo->lastInsertId();
                    }
                    $deactivateVariants = $pdo->prepare('UPDATE product_variants SET active = 0 WHERE product_id = :product_id');
                    $deactivateVariants->execute(['product_id' => $productId]);
                    $saveVariant = $pdo->prepare(
                        'INSERT INTO product_variants (product_id, label, stock_quantity, active)
                         VALUES (:product_id, :label, :stock_quantity, 1)
                         ON CONFLICT(product_id, label) DO UPDATE SET stock_quantity = excluded.stock_quantity, active = 1'
                    );
                    foreach ($variants as $label => $variantStock) {
                        $saveVariant->execute([
                            'product_id' => $productId,
                            'label' => $label,
                            'stock_quantity' => $variantStock,
                        ]);
                    }
                    save_product_search_terms($productId, $searchTerms);
                    $pdo->commit();
                } catch (Throwable $error) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $error;
                }
                flash('admin', $id !== false && $id !== null ? 'পণ্যের তথ্য আপডেট হয়েছে।' : 'নতুন পণ্য যোগ হয়েছে।');
                redirect('admin.php?view=products');
            }
        } elseif ($action === 'deactivate_product') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id !== false) {
                $statement = db()->prepare('UPDATE products SET active = 0 WHERE id = :id');
                $statement->execute(['id' => $id]);
                flash('admin', 'পণ্যটি স্টোর থেকে সরানো হয়েছে।');
                redirect('admin.php?view=products');
            }
        } elseif ($action === 'update_order') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $status = (string) ($_POST['order_status'] ?? '');
            $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            if ($id === false || !in_array($status, $validStatuses, true)) {
                $errors[] = 'অর্ডারের অবস্থা সঠিক নয়।';
            } else {
                $currentOrderQuery = db()->prepare('SELECT order_status, payment_status FROM orders WHERE id = :id');
                $currentOrderQuery->execute(['id' => $id]);
                $currentOrder = $currentOrderQuery->fetch();
                if ($currentOrder === false) {
                    $errors[] = 'অর্ডারটি পাওয়া যায়নি।';
                } elseif ($currentOrder['order_status'] === 'cancelled' && $status !== 'cancelled') {
                    $errors[] = 'বাতিল অর্ডার পুনরায় চালু করা যাবে না; নতুন অর্ডার তৈরি করুন।';
                } elseif ($status === 'cancelled' && in_array($currentOrder['order_status'], ['shipped', 'delivered'], true)) {
                    $errors[] = 'পণ্য পাঠানো বা ডেলিভারির পর cancellation নয়—return/refund workflow ব্যবহার করতে হবে।';
                } elseif ($status === 'cancelled' && $currentOrder['payment_status'] === 'paid') {
                    $errors[] = 'পেমেন্ট সম্পন্ন অর্ডার বাতিলের আগে refund সম্পন্ন করুন।';
                } else {
                    if ($status === 'cancelled') {
                        release_coupon_reservation((int) $id);
                        release_order_stock((int) $id);
                    }
                    $statement = db()->prepare('UPDATE orders SET order_status = :status WHERE id = :id');
                    $statement->execute(['status' => $status, 'id' => $id]);
                    flash('admin', 'অর্ডারের অবস্থা আপডেট হয়েছে।');
                    redirect('admin.php?view=order&id=' . $id);
                }
            }
        } elseif ($action === 'mark_cod_paid') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id !== false) {
                $statement = db()->prepare('UPDATE orders SET payment_status = "paid" WHERE id = :id AND payment_method = "cod" AND order_status != "cancelled"');
                $statement->execute(['id' => $id]);
                flash('admin', $statement->rowCount() > 0 ? 'ক্যাশ পেমেন্ট গ্রহণ হিসেবে চিহ্নিত হয়েছে।' : 'শুধু সক্রিয় ক্যাশ অন ডেলিভারি অর্ডারে এই কাজ করা যায়।');
                redirect('admin.php?view=order&id=' . $id);
            }
        } elseif ($action === 'save_coupon') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $type = (string) ($_POST['discount_type'] ?? '');
            $value = filter_var($_POST['discount_value'] ?? null, FILTER_VALIDATE_INT);
            $minimum = filter_var($_POST['minimum_order'] ?? 0, FILTER_VALIDATE_INT);
            $maximumInput = trim((string) ($_POST['maximum_discount'] ?? ''));
            $maximum = $maximumInput === '' ? null : filter_var($maximumInput, FILTER_VALIDATE_INT);
            $limitInput = trim((string) ($_POST['usage_limit'] ?? ''));
            $limit = $limitInput === '' ? null : filter_var($limitInput, FILTER_VALIDATE_INT);
            $endInput = trim((string) ($_POST['ends_at'] ?? ''));
            $endTime = $endInput === '' ? null : strtotime($endInput);
            if (!preg_match('/^[A-Z0-9_-]{3,40}$/', $code)) {
                $errors[] = 'কুপন কোডে ৩–৪০টি ইংরেজি অক্ষর, সংখ্যা, _ বা - ব্যবহার করুন।';
            }
            if (!in_array($type, ['percent', 'fixed'], true) || $value === false || $value < 1 || ($type === 'percent' && $value > 100)) {
                $errors[] = 'ছাড়ের ধরন বা পরিমাণ সঠিক নয়।';
            }
            if ($minimum === false || $minimum < 0 || ($maximum !== null && ($maximum === false || $maximum < 0)) || ($limit !== null && ($limit === false || $limit < 1))) {
                $errors[] = 'সর্বনিম্ন অর্ডার, সর্বোচ্চ ছাড় অথবা ব্যবহারের সীমা সঠিক নয়।';
            }
            if ($endInput !== '' && ($endTime === false || $endTime <= time())) {
                $errors[] = 'কুপনের মেয়াদ শেষের সময় ভবিষ্যতের হতে হবে।';
            }
            if ($errors === []) {
                $parameters = [
                    'code' => $code, 'type' => $type, 'value' => $value, 'minimum' => $minimum,
                    'maximum' => $maximum, 'limit' => $limit,
                    'ends_at' => $endTime === null ? null : date('Y-m-d H:i:s', $endTime),
                ];
                try {
                    if ($id !== false && $id !== null) {
                        $statement = db()->prepare(
                            'UPDATE coupons SET code = :code, discount_type = :type, discount_value = :value,
                             minimum_order = :minimum, maximum_discount = :maximum, usage_limit = :limit, ends_at = :ends_at WHERE id = :id'
                        );
                        $statement->execute($parameters + ['id' => $id]);
                        flash('admin', 'কুপনের তথ্য আপডেট হয়েছে।');
                    } else {
                        $statement = db()->prepare(
                            'INSERT INTO coupons (code, discount_type, discount_value, minimum_order, maximum_discount, usage_limit, ends_at)
                             VALUES (:code, :type, :value, :minimum, :maximum, :limit, :ends_at)'
                        );
                        $statement->execute($parameters);
                        flash('admin', 'নতুন কুপন তৈরি হয়েছে।');
                    }
                    redirect('admin.php?view=coupons');
                } catch (PDOException $error) {
                    if ($error->getCode() === '23000') {
                        $errors[] = 'এই কুপন কোডটি আগে থেকেই আছে।';
                    } else {
                        throw $error;
                    }
                }
            }
        } elseif ($action === 'toggle_coupon') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id !== false) {
                $statement = db()->prepare('UPDATE coupons SET active = 1 - active WHERE id = :id');
                $statement->execute(['id' => $id]);
                flash('admin', 'কুপনের অবস্থা আপডেট হয়েছে।');
                redirect('admin.php?view=coupons');
            }
        } elseif ($action === 'moderate_review') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $status = (string) ($_POST['status'] ?? '');
            if ($id === false || !in_array($status, ['approved', 'rejected'], true)) {
                $errors[] = 'রিভিউর অবস্থা সঠিক নয়।';
            } else {
                $statement = db()->prepare('UPDATE product_reviews SET status = :status WHERE id = :id');
                $statement->execute(['status' => $status, 'id' => $id]);
                flash('admin', 'রিভিউর অবস্থা আপডেট হয়েছে।');
                redirect('admin.php?view=reviews');
            }
        } elseif ($action === 'update_contact_status') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $status = (string) ($_POST['status'] ?? '');
            if ($id === false || !in_array($status, ['new', 'in_progress', 'closed'], true)) {
                $errors[] = 'বার্তার অবস্থা সঠিক নয়।';
            } else {
                $statement = db()->prepare('UPDATE contact_messages SET status = :status WHERE id = :id');
                $statement->execute(['status' => $status, 'id' => $id]);
                if ($statement->rowCount() !== 1) {
                    $errors[] = 'বার্তাটি পাওয়া যায়নি।';
                } else {
                    flash('admin', 'বার্তার অবস্থা আপডেট হয়েছে।');
                    redirect('admin.php?view=messages');
                }
            }
        }
    }
}

function admin_status(string $value): string
{
    $labels = [
        'pending' => 'নতুন', 'processing' => 'প্রস্তুত হচ্ছে', 'shipped' => 'পথে আছে',
        'delivered' => 'ডেলিভারি সম্পন্ন', 'cancelled' => 'বাতিল',
        'unpaid' => 'পেমেন্ট বাকি', 'paid' => 'পেমেন্ট সম্পন্ন', 'failed' => 'ব্যর্থ',
    ];
    return $labels[$value] ?? $value;
}

function admin_payment_status(string $value): string
{
    return [
        'unpaid' => 'পেমেন্ট বাকি',
        'pending' => 'যাচাই হচ্ছে',
        'paid' => 'পেমেন্ট সম্পন্ন',
        'failed' => 'পেমেন্ট ব্যর্থ',
        'approved' => 'অনুমোদিত',
        'rejected' => 'প্রকাশিত নয়',
        'active' => 'চালু',
        'inactive' => 'বন্ধ',
    ][$value] ?? $value;
}

function admin_contact_status(string $value): string
{
    return [
        'new' => 'নতুন',
        'in_progress' => 'কাজ চলছে',
        'closed' => 'সম্পন্ন',
    ][$value] ?? $value;
}

if ($view === 'login') {
    $setupReady = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0;
    page_header('অ্যাডমিন লগইন');
    ?>
    <main class="inner-page admin-login-page"><form class="admin-login-card" method="post" action="admin.php?view=login"><span class="eyebrow">ALL SHOP · ADMIN</span><h1>অ্যাডমিন <em>লগইন</em></h1>
        <?php if (!$setupReady): ?><p class="form-error">প্রথমে .env-এ ALLSHOP_ADMIN_USERNAME ও ALLSHOP_ADMIN_PASSWORD সেট করে পেজটি আবার খুলুন।</p><?php endif; ?>
        <?php if ($errors !== []): ?><div class="form-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>ইউজারনেম<input name="username" autocomplete="username" required></label><label>পাসওয়ার্ড<input type="password" name="password" autocomplete="current-password" required></label><button class="button button-dark button-full" type="submit" <?= !$setupReady ? 'disabled' : '' ?>>লগইন <span>↗</span></button><a class="admin-back-link" href="index.php">← দোকানে ফিরে যান</a>
    </form></main>
    <?php
    page_footer();
    exit;
}

$adminName = e((string) $_SESSION['admin_user']);
$orderStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
page_header('অ্যাডমিন');
?>
<main class="admin-page">
    <aside class="admin-sidebar"><a class="brand footer-logo" href="admin.php"><span class="brand-mark">a</span><span>all<span class="brand-light">shop</span><small>ADMIN PANEL</small></span></a>
        <nav><a class="<?= $view === 'dashboard' ? 'active' : '' ?>" href="admin.php">▦ <span>ড্যাশবোর্ড</span></a><a class="<?= in_array($view, ['products', 'product'], true) ? 'active' : '' ?>" href="admin.php?view=products">◇ <span>পণ্য</span></a><a class="<?= in_array($view, ['orders', 'order'], true) ? 'active' : '' ?>" href="admin.php?view=orders">▤ <span>অর্ডার</span></a><a class="<?= in_array($view, ['seller_applications', 'seller_products'], true) ? 'active' : '' ?>" href="admin.php?view=seller_applications">♧ <span>সেলার</span></a><a class="<?= $view === 'coupons' ? 'active' : '' ?>" href="admin.php?view=coupons">％ <span>কুপন</span></a><a class="<?= $view === 'reviews' ? 'active' : '' ?>" href="admin.php?view=reviews">★ <span>রিভিউ</span></a><a class="<?= $view === 'search_analytics' ? 'active' : '' ?>" href="admin.php?view=search_analytics">⌕ <span>সার্চ ডেটা</span></a><a class="<?= $view === 'messages' ? 'active' : '' ?>" href="admin.php?view=messages">✉ <span>বার্তা</span></a><a class="<?= $view === 'subscribers' ? 'active' : '' ?>" href="admin.php?view=subscribers">✉ <span>সাবস্ক্রাইবার</span></a></nav>
        <a class="admin-store-link" href="index.php">← দোকান দেখুন</a><form method="post" action="admin.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button type="submit" class="admin-logout">লগআউট · <?= $adminName ?></button></form>
    </aside>
    <section class="admin-content">
        <div class="admin-topline"><span>All Shop / <?= e(ucfirst($view)) ?></span><span class="admin-avatar"><?= e(strtoupper(substr((string) $_SESSION['admin_user'], 0, 1))) ?></span></div>
        <?php if ($notice !== null): ?><p class="notice"><?= e($notice) ?></p><?php endif; ?>
        <?php if ($errors !== []): ?><div class="form-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <?php if ($view === 'dashboard'): ?>
            <?php
            $metrics = [
                'মোট অর্ডার' => (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
                'নতুন অর্ডার' => (int) db()->query('SELECT COUNT(*) FROM orders WHERE order_status = "pending"')->fetchColumn(),
                'সক্রিয় পণ্য' => (int) db()->query('SELECT COUNT(*) FROM products WHERE active = 1')->fetchColumn(),
                'কম স্টক' => (int) db()->query(
                    'SELECT COUNT(*) FROM products p
                     WHERE p.active = 1 AND (
                         (NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.active = 1)
                          AND p.track_inventory = 1 AND p.stock_quantity <= p.low_stock_threshold)
                         OR EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.active = 1 AND v.stock_quantity <= p.low_stock_threshold)
                     )'
                )->fetchColumn(),
                'সাবস্ক্রাইবার' => (int) db()->query('SELECT COUNT(*) FROM newsletter_subscribers')->fetchColumn(),
                'সেলার আবেদন' => (int) db()->query('SELECT COUNT(*) FROM seller_applications WHERE status = "pending"')->fetchColumn(),
                'পণ্য রিভিউ' => (int) db()->query('SELECT COUNT(*) FROM seller_product_submissions WHERE status = "submitted"')->fetchColumn(),
            ];
            $latest = db()->query('SELECT id, order_number, customer_name, total, payment_status, order_status, created_at FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
            ?>
            <div class="admin-page-title"><div><span class="eyebrow">WELCOME BACK</span><h1>ড্যাশবোর্ড</h1><p>আপনার স্টোরের সাম্প্রতিক কার্যকলাপ দেখুন।</p></div><a class="button button-dark" href="index.php">দোকান দেখুন <span>↗</span></a></div>
            <div class="admin-metrics"><?php foreach ($metrics as $label => $value): ?><article><span><?= e($label) ?></span><strong><?= $value ?></strong><small>All Shop</small></article><?php endforeach; ?></div>
            <section class="admin-panel"><div class="admin-panel-heading"><h2>সাম্প্রতিক অর্ডার</h2><a href="admin.php?view=orders">সব অর্ডার ↗</a></div>
                <?php if ($latest === []): ?><div class="admin-empty">এখনো কোনো অর্ডার নেই।</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>অর্ডার</th><th>ক্রেতা</th><th>মোট</th><th>অবস্থা</th><th>সময়</th><th></th></tr></thead><tbody>
                    <?php foreach ($latest as $order): ?><tr><td><strong><?= e($order['order_number']) ?></strong></td><td><?= e($order['customer_name']) ?></td><td><?= taka((int) $order['total']) ?></td><td><span class="status-chip"><?= e(admin_status($order['order_status'])) ?></span></td><td><?= e($order['created_at']) ?></td><td><a class="table-link" href="admin.php?view=order&id=<?= (int) $order['id'] ?>">বিস্তারিত</a></td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>
            </section>

        <?php elseif ($view === 'products'): ?>
            <?php $productRows = products(); $productRows = array_reverse($productRows, true); ?>
            <div class="admin-page-title"><div><span class="eyebrow">আপনার কালেকশন</span><h1>পণ্য</h1><p>স্টোরের পণ্য যোগ করুন ও আপডেট করুন।</p></div><a class="button button-dark" href="admin.php?view=product">+ নতুন পণ্য</a></div>
            <section class="admin-panel"><div class="table-wrap"><table><thead><tr><th>পণ্য</th><th>ক্যাটাগরি</th><th>মূল্য</th><th>স্টক</th><th>ব্যাজ</th><th></th></tr></thead><tbody>
                <?php foreach ($productRows as $product): $stock = product_available_stock($product); $lowStock = product_has_low_stock($product); ?><tr><td><div class="admin-product-cell"><img src="<?= e($product['image']) ?>" alt=""><strong><?= e($product['name']) ?></strong></div></td><td><?= e($product['category']) ?></td><td><?= taka((int) $product['price']) ?></td><td><?php if ($stock === null): ?>গণনা বন্ধ<?php else: ?><?= $stock ?>টি<?= $lowStock ? ($product['has_variants'] ? ' · ভ্যারিয়েন্টে কম' : ' · কম') : '' ?><?php endif; ?></td><td><?= e($product['badge'] ?: '—') ?></td><td><a class="table-link" href="admin.php?view=product&id=<?= (int) $product['id'] ?>">সম্পাদনা</a></td></tr><?php endforeach; ?>
            </tbody></table></div></section>

        <?php elseif ($view === 'product'): ?>
            <?php
            $editId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            $editingProduct = $editId !== false && $editId !== null ? product_by_id((int) $editId, true) : null;
            if ($editId !== false && $editId !== null && $editingProduct === null) {
                echo '<p class="form-error">পণ্যটি পাওয়া যায়নি।</p>';
            }
            $formProduct = $editingProduct ?? ['id' => '', 'name' => '', 'category' => '', 'price' => '', 'old_price' => '', 'badge' => '', 'image' => '', 'description' => '', 'track_inventory' => 1, 'stock_quantity' => 0, 'low_stock_threshold' => 5, 'has_variants' => false, 'search_terms' => ''];
            if ($editingProduct) {
                $formProduct['search_terms'] = implode(', ', product_search_terms((int) $editingProduct['id']));
            }
            $variantRows = $editingProduct ? product_variants((int) $editingProduct['id']) : [];
            $variantText = implode("\n", array_map(static fn(array $variant): string => $variant['label'] . ' | ' . $variant['stock_quantity'], $variantRows));
            if ($editingProduct && $editingProduct['has_variants']) {
                $formProduct['stock_quantity'] = $editingProduct['variant_stock'];
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_product') {
                $formProduct = array_merge($formProduct, $_POST);
                $formProduct['track_inventory'] = isset($_POST['track_inventory']) ? 1 : 0;
                $variantText = (string) ($_POST['variants'] ?? '');
            }
            ?>
            <div class="admin-page-title"><div><span class="eyebrow">CATALOGUE</span><h1><?= $editingProduct ? 'পণ্য সম্পাদনা' : 'নতুন পণ্য' ?></h1></div><a class="text-button" href="admin.php?view=products">← পণ্যে ফিরে যান</a></div>
            <form class="admin-panel admin-product-form" method="post" action="admin.php?view=product<?= $editingProduct ? '&id=' . (int) $editingProduct['id'] : '' ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?= e((string) $formProduct['id']) ?>">
                <label>পণ্যের নাম<input name="name" maxlength="120" required value="<?= e((string) $formProduct['name']) ?>"></label><label>ক্যাটাগরি<input name="category" maxlength="60" required value="<?= e((string) $formProduct['category']) ?>"></label>
                <label>বর্তমান মূল্য (৳)<input type="number" min="0" max="100000000" name="price" required value="<?= e((string) $formProduct['price']) ?>"></label><label>আগের মূল্য (ঐচ্ছিক)<input type="number" min="0" max="100000000" name="old_price" value="<?= e((string) ($formProduct['old_price'] ?? '')) ?>"></label>
                <label>ব্যাজ (ঐচ্ছিক)<input name="badge" maxlength="30" value="<?= e((string) $formProduct['badge']) ?>" placeholder="NEW / SALE"></label><label>ছবির HTTPS লিংক<input name="image" required value="<?= e((string) $formProduct['image']) ?>"></label>
                <label class="admin-form-wide">পণ্যের বিবরণ<textarea name="description" rows="4" maxlength="2000" required><?= e((string) $formProduct['description']) ?></textarea></label>
                <label class="admin-form-wide">সার্চ শব্দ (কমা দিয়ে আলাদা করুন)<input name="search_terms" maxlength="1800" value="<?= e((string) ($formProduct['search_terms'] ?? '')) ?>" placeholder="ব্যাগ, bag, mobail"><small class="inventory-help">বাংলা, English, Banglish ও প্রচলিত বানান যোগ করুন; এগুলো শুধু এই পণ্যের খোঁজে ব্যবহৃত হবে।</small></label>
                <fieldset class="admin-inventory admin-form-wide"><legend>স্টক ও ভ্যারিয়েন্ট</legend>
                    <label class="inventory-checkbox"><input type="checkbox" name="track_inventory" value="1" <?= (int) ($formProduct['track_inventory'] ?? 0) === 1 ? 'checked' : '' ?>> এই পণ্যের স্টক হিসাব রাখুন</label>
                    <label>সাধারণ স্টক<input type="number" name="stock_quantity" min="0" max="<?= !empty($formProduct['has_variants']) ? '50000000' : '1000000' ?>" value="<?= e((string) ($formProduct['stock_quantity'] ?? 0)) ?>"></label>
                    <label>কম-স্টক সতর্কতার সীমা<input type="number" name="low_stock_threshold" min="0" max="1000000" value="<?= e((string) ($formProduct['low_stock_threshold'] ?? 5)) ?>"></label>
                    <label class="admin-form-wide">ভ্যারিয়েন্ট (প্রতি লাইনে: নাম | স্টক)<textarea name="variants" rows="4" placeholder="লাল / M | 8&#10;নীল / L | 3"><?= e($variantText) ?></textarea></label>
                    <small class="inventory-help">ভ্যারিয়েন্ট দিলে প্রতিটির স্টক আলাদা গণনা হবে এবং সাধারণ স্টক স্বয়ংক্রিয়ভাবে বন্ধ থাকবে। খালি রাখলে শুধু সাধারণ স্টক ব্যবহার হবে।</small>
                </fieldset>
                <div class="admin-form-actions"><button class="button button-dark" type="submit">পণ্য সংরক্ষণ করুন <span>↗</span></button><?php if ($editingProduct): ?><button class="button button-quiet" type="submit" name="action" value="deactivate_product" form="deactivate-product">স্টোর থেকে সরান</button><?php endif; ?></div>
            </form>
            <?php if ($editingProduct): ?><form id="deactivate-product" method="post" action="admin.php?view=products"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="deactivate_product"><input type="hidden" name="id" value="<?= (int) $editingProduct['id'] ?>"></form><?php endif; ?>

        <?php elseif ($view === 'orders'): ?>
            <?php $orders = db()->query('SELECT id, order_number, customer_name, phone, total, payment_method, payment_status, order_status, created_at FROM orders ORDER BY id DESC')->fetchAll(); ?>
            <div class="admin-page-title"><div><span class="eyebrow">SALES &amp; DELIVERY</span><h1>অর্ডার</h1><p>গ্রাহকের অর্ডার ও পেমেন্টের অবস্থা।</p></div></div>
            <section class="admin-panel"><div class="table-wrap"><table><thead><tr><th>অর্ডার</th><th>ক্রেতা</th><th>মোট</th><th>পেমেন্ট</th><th>ডেলিভারি</th><th>তারিখ</th><th></th></tr></thead><tbody>
                <?php foreach ($orders as $order): ?><tr><td><strong><?= e($order['order_number']) ?></strong></td><td><?= e($order['customer_name']) ?><small class="table-sub"><?= e($order['phone']) ?></small></td><td><?= taka((int) $order['total']) ?></td><td><span class="status-chip"><?= e(admin_payment_status($order['payment_status'])) ?></span></td><td><?= e(admin_status($order['order_status'])) ?></td><td><?= e($order['created_at']) ?></td><td><a class="table-link" href="admin.php?view=order&id=<?= (int) $order['id'] ?>">বিস্তারিত</a></td></tr><?php endforeach; ?>
                <?php if ($orders === []): ?><tr><td colspan="7">এখনো কোনো অর্ডার নেই।</td></tr><?php endif; ?>
            </tbody></table></div></section>

        <?php elseif ($view === 'order'): ?>
            <?php
            $orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            $orderQuery = db()->prepare('SELECT * FROM orders WHERE id = :id');
            $orderQuery->execute(['id' => $orderId === false ? 0 : $orderId]);
            $order = $orderQuery->fetch();
            ?>
            <?php if (!$order): ?><div class="admin-page-title"><h1>অর্ডার পাওয়া যায়নি</h1><a href="admin.php?view=orders">← অর্ডারে ফিরে যান</a></div>
            <?php else: ?>
                <?php $lineItems = db()->prepare('SELECT product_name, variant_label, quantity, unit_price FROM order_items WHERE order_id = :id'); $lineItems->execute(['id' => $order['id']]); $orderItems = $lineItems->fetchAll(); ?>
                <div class="admin-page-title"><div><span class="eyebrow"><?= e($order['order_number']) ?></span><h1>অর্ডারের বিস্তারিত</h1><p><?= e($order['created_at']) ?></p></div><a class="text-button" href="admin.php?view=orders">← সব অর্ডার</a></div>
                <div class="admin-order-grid"><section class="admin-panel"><h2>পণ্যসমূহ</h2><div class="confirmation-items"><?php foreach ($orderItems as $item): ?><div><span><?= e($item['product_name']) ?><?= $item['variant_label'] !== null ? ' · ' . e($item['variant_label']) : '' ?> × <?= (int) $item['quantity'] ?></span><strong><?= taka((int) $item['unit_price'] * (int) $item['quantity']) ?></strong></div><?php endforeach; ?></div><div class="summary-row"><span>ডেলিভারি</span><span><?= (int) $order['delivery'] === 0 ? 'ফ্রি' : taka((int) $order['delivery']) ?></span></div><div class="summary-total"><span>মোট</span><strong><?= taka((int) $order['total']) ?></strong></div></section>
                    <section class="admin-panel"><h2>ক্রেতার তথ্য</h2><p><strong><?= e($order['customer_name']) ?></strong><br><?= e($order['phone']) ?></p><p><?= nl2br(e($order['address'])) ?></p><p>পেমেন্ট: <?= e($order['payment_method'] === 'bkash' ? 'bKash' : 'ক্যাশ অন ডেলিভারি') ?> · <?= e(admin_payment_status($order['payment_status'])) ?></p><?php if ($order['transaction_id']): ?><p>লেনদেন নম্বর: <strong><?= e($order['transaction_id']) ?></strong></p><?php endif; ?>
                        <form class="admin-order-status" method="post" action="admin.php?view=order&id=<?= (int) $order['id'] ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_order"><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><label>ডেলিভারি স্ট্যাটাস<select name="order_status"><?php foreach ($orderStatuses as $status): ?><option value="<?= e($status) ?>" <?= $order['order_status'] === $status ? 'selected' : '' ?>><?= e(admin_status($status)) ?></option><?php endforeach; ?></select></label><button class="button button-dark" type="submit">আপডেট করুন</button></form>
                        <?php if ($order['payment_method'] === 'cod' && $order['payment_status'] !== 'paid'): ?><form method="post" action="admin.php?view=order&id=<?= (int) $order['id'] ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="mark_cod_paid"><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><button class="text-button" type="submit">ক্যাশ পেমেন্ট গ্রহণ হিসেবে চিহ্নিত করুন</button></form><?php endif; ?>
                    </section>
                </div>
            <?php endif; ?>

        <?php elseif ($view === 'subscribers'): ?>
            <?php $subscribers = db()->query('SELECT email, created_at FROM newsletter_subscribers ORDER BY id DESC')->fetchAll(); ?>
            <div class="admin-page-title"><div><span class="eyebrow">ALL SHOP LETTER</span><h1>সাবস্ক্রাইবার</h1><p><?= count($subscribers) ?>টি ইমেইল তালিকাভুক্ত।</p></div></div>
            <section class="admin-panel"><div class="table-wrap"><table><thead><tr><th>ইমেইল</th><th>যুক্ত হওয়ার তারিখ</th></tr></thead><tbody><?php foreach ($subscribers as $subscriber): ?><tr><td><?= e($subscriber['email']) ?></td><td><?= e($subscriber['created_at']) ?></td></tr><?php endforeach; ?><?php if ($subscribers === []): ?><tr><td colspan="2">এখনো কেউ সাবস্ক্রাইব করেননি।</td></tr><?php endif; ?></tbody></table></div></section>

        <?php elseif ($view === 'messages'): ?>
            <?php
            $messageCount = (int) db()->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
            $messages = db()->query('SELECT * FROM contact_messages ORDER BY CASE status WHEN "new" THEN 0 WHEN "in_progress" THEN 1 ELSE 2 END, id DESC LIMIT 100')->fetchAll();
            ?>
            <div class="admin-page-title"><div><span class="eyebrow">CUSTOMER CARE</span><h1>ক্রেতার বার্তা</h1><p>মোট <?= $messageCount ?>টি বার্তা · সর্বশেষ ১০০টি দেখানো হচ্ছে।</p></div></div>
            <?php if ($messages === []): ?><section class="admin-panel admin-empty">এখনো কোনো বার্তা আসেনি।</section><?php else: ?>
                <div class="admin-messages"><?php foreach ($messages as $message): ?>
                    <article class="admin-panel admin-message">
                        <div class="admin-message-heading"><div><span class="status-chip"><?= e(admin_contact_status($message['status'])) ?></span><h2><?= e($message['subject']) ?></h2></div><small><?= e($message['created_at']) ?></small></div>
                        <p class="admin-message-sender"><strong><?= e($message['name']) ?></strong> · <a href="mailto:<?= e($message['email']) ?>"><?= e($message['email']) ?></a><?= $message['phone'] !== '' ? ' · ' . e($message['phone']) : '' ?></p>
                        <p class="admin-message-body"><?= nl2br(e($message['message'])) ?></p>
                        <form class="admin-message-status" method="post" action="admin.php?view=messages">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update_contact_status">
                            <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
                            <label>অবস্থা<select name="status"><?php foreach (['new', 'in_progress', 'closed'] as $status): ?><option value="<?= e($status) ?>" <?= $message['status'] === $status ? 'selected' : '' ?>><?= e(admin_contact_status($status)) ?></option><?php endforeach; ?></select></label>
                            <button class="button button-dark" type="submit">আপডেট</button>
                        </form>
                    </article>
                <?php endforeach; ?></div>
            <?php endif; ?>

        <?php elseif ($view === 'coupons'): ?>
            <?php $coupons = db()->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll(); ?>
            <div class="admin-page-title"><div><span class="eyebrow">PROMOTIONS</span><h1>কুপন ও ছাড়</h1><p>ALLSHOP10 ডিফল্ট কুপন: ৳১,০০০+ অর্ডারে ১০% ছাড় (সর্বোচ্চ ৳৫০০)।</p></div></div>
            <form class="admin-panel admin-coupon-form" method="post" action="admin.php?view=coupons">
                <h2>নতুন কুপন তৈরি করুন</h2><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_coupon">
                <label>কুপন কোড<input name="code" maxlength="40" required placeholder="SAVE15"></label>
                <label>ছাড়ের ধরন<select name="discount_type"><option value="percent">শতাংশ (%)</option><option value="fixed">নির্দিষ্ট টাকা (৳)</option></select></label>
                <label>ছাড়ের পরিমাণ<input type="number" min="1" name="discount_value" required></label>
                <label>সর্বনিম্ন অর্ডার (৳)<input type="number" min="0" name="minimum_order" value="0"></label>
                <label>সর্বোচ্চ ছাড় (৳)<input type="number" min="0" name="maximum_discount" placeholder="সীমা নেই"></label>
                <label>ব্যবহারের সর্বোচ্চ সংখ্যা<input type="number" min="1" name="usage_limit" placeholder="সীমা নেই"></label>
                <label>মেয়াদ শেষ<input type="datetime-local" name="ends_at"></label>
                <div class="admin-form-actions"><button class="button button-dark" type="submit">কুপন তৈরি করুন <span>↗</span></button></div>
            </form>
            <section class="admin-panel coupons-list"><h2>বর্তমান কুপন</h2><div class="table-wrap"><table><thead><tr><th>কোড</th><th>ছাড়</th><th>সর্বনিম্ন</th><th>ব্যবহার</th><th>মেয়াদ</th><th>অবস্থা</th><th></th></tr></thead><tbody>
                <?php foreach ($coupons as $coupon): ?><tr><td><strong><?= e($coupon['code']) ?></strong></td><td><?= $coupon['discount_type'] === 'percent' ? (int) $coupon['discount_value'] . '%' : taka((int) $coupon['discount_value']) ?><?php if ($coupon['maximum_discount'] !== null): ?><small class="table-sub">সর্বোচ্চ <?= taka((int) $coupon['maximum_discount']) ?></small><?php endif; ?></td><td><?= taka((int) $coupon['minimum_order']) ?></td><td><?= (int) $coupon['usage_count'] ?> / <?= $coupon['usage_limit'] === null ? '∞' : (int) $coupon['usage_limit'] ?></td><td><?= e($coupon['ends_at'] ?? 'নেই') ?></td><td><?= e(admin_status($coupon['active'] ? 'active' : 'inactive')) ?></td><td><form method="post" action="admin.php?view=coupons"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle_coupon"><input type="hidden" name="id" value="<?= (int) $coupon['id'] ?>"><button class="table-link table-button" type="submit"><?= $coupon['active'] ? 'বন্ধ করুন' : 'চালু করুন' ?></button></form></td></tr><?php endforeach; ?>
            </tbody></table></div></section>

        <?php elseif ($view === 'seller_applications'): ?>
            <?php
            $applications = db()->query(
                'SELECT id, business_name, owner_name, email, phone, business_address, categories, website,
                        application_note, status, business_verified, phone_verified, payout_verified,
                        legal_tax_verified, admin_notes, reviewed_by, reviewed_at, created_at
                 FROM seller_applications
                 ORDER BY CASE status WHEN "pending" THEN 0 ELSE 1 END, id DESC LIMIT 100'
            )->fetchAll();
            $pendingApplications = (int) db()->query('SELECT COUNT(*) FROM seller_applications WHERE status = "pending"')->fetchColumn();
            ?>
            <div class="admin-page-title"><div><span class="eyebrow">MARKETPLACE · SELLER VERIFICATION</span><h1>সেলার আবেদন</h1><p>অনুমোদনের আগে ব্যবসা, ফোন, payout ownership ও প্রয়োজনীয় legal/tax তথ্য হাতে যাচাই করুন। আবেদন: <?= $pendingApplications ?>টি pending।</p></div><a class="button button-quiet" href="admin.php?view=seller_products">পণ্য review ↗</a></div>
            <?php if ($applications === []): ?><section class="admin-panel admin-empty">এখনো কোনো সেলার আবেদন নেই।</section><?php endif; ?>
            <div class="admin-reviews seller-review-list">
                <?php foreach ($applications as $application): ?>
                    <article class="admin-panel seller-review-card">
                        <div class="admin-panel-heading"><h2><?= e($application['business_name']) ?></h2><span class="status-chip"><?= e($application['status'] === 'pending' ? 'যাচাই বাকি' : ($application['status'] === 'approved' ? 'অনুমোদিত' : 'প্রত্যাখ্যাত')) ?></span></div>
                        <p><strong><?= e($application['owner_name']) ?></strong> · <a href="mailto:<?= e($application['email']) ?>"><?= e($application['email']) ?></a> · <?= e($application['phone']) ?></p>
                        <p>ঠিকানা: <?= e($application['business_address']) ?><br>ক্যাটাগরি: <?= e($application['categories']) ?><?= $application['website'] !== '' ? '<br>ওয়েবসাইট: <a href="' . e($application['website']) . '" target="_blank" rel="noopener noreferrer">' . e($application['website']) . '</a>' : '' ?></p>
                        <?php if ($application['application_note'] !== ''): ?><p>আবেদনকারীর তথ্য: <?= nl2br(e($application['application_note'])) ?></p><?php endif; ?>
                        <small>জমা: <?= e($application['created_at']) ?><?= $application['reviewed_at'] !== null ? ' · Review: ' . e($application['reviewed_at']) . ' · ' . e((string) $application['reviewed_by']) : '' ?></small>
                        <?php if ($application['status'] === 'pending'): ?>
                            <form class="seller-review-form" method="post" action="admin.php?view=seller_applications">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_seller_application"><input type="hidden" name="id" value="<?= (int) $application['id'] ?>">
                                <fieldset><legend>যাচাই সম্পন্ন (approve করতে সবগুলো আবশ্যক)</legend>
                                    <label><input type="checkbox" name="business_verified" value="1"> ব্যবসা/পরিচয়</label>
                                    <label><input type="checkbox" name="phone_verified" value="1"> ফোন</label>
                                    <label><input type="checkbox" name="payout_verified" value="1"> payout account ownership</label>
                                    <label><input type="checkbox" name="legal_tax_verified" value="1"> প্রয়োজনীয় legal/tax তথ্য</label>
                                </fieldset>
                                <label>প্রশাসনিক নোট<textarea name="admin_notes" maxlength="1000" rows="3"></textarea></label>
                                <div class="admin-form-actions"><button class="button button-dark" type="submit" name="decision" value="approved">যাচাই করে অনুমোদন</button><button class="button button-quiet" type="submit" name="decision" value="rejected">প্রত্যাখ্যান</button></div>
                            </form>
                        <?php else: ?>
                            <p class="seller-admin-note">যাচাই: ব্যবসা <?= (int) $application['business_verified'] ? '✓' : '—' ?> · ফোন <?= (int) $application['phone_verified'] ? '✓' : '—' ?> · payout <?= (int) $application['payout_verified'] ? '✓' : '—' ?> · legal/tax <?= (int) $application['legal_tax_verified'] ? '✓' : '—' ?><br>নোট: <?= e($application['admin_notes'] ?: '—') ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php elseif ($view === 'seller_products'): ?>
            <?php
            $sellerSubmissions = db()->query(
                'SELECT p.*, s.business_name, s.email
                 FROM seller_product_submissions p JOIN sellers s ON s.id = p.seller_id
                 ORDER BY CASE p.status WHEN "submitted" THEN 0 ELSE 1 END, p.id DESC LIMIT 100'
            )->fetchAll();
            ?>
            <div class="admin-page-title"><div><span class="eyebrow">MARKETPLACE · CATALOG REVIEW</span><h1>সেলার পণ্য</h1><p>অনুমোদন catalog review-এর জন্য; এই পর্যায়ে কোনো পণ্য live storefront, cart বা checkout-এ যুক্ত হয় না।</p></div><a class="button button-quiet" href="admin.php?view=seller_applications">সেলার আবেদন ↗</a></div>
            <?php if ($sellerSubmissions === []): ?><section class="admin-panel admin-empty">এখনো কোনো সেলার পণ্য জমা পড়েনি।</section><?php endif; ?>
            <div class="admin-reviews seller-review-list">
                <?php foreach ($sellerSubmissions as $submission): ?>
                    <article class="admin-panel seller-review-card">
                        <div class="admin-panel-heading"><h2><?= e($submission['name']) ?></h2><span class="status-chip"><?= e(match ($submission['status']) { 'draft' => 'Draft', 'submitted' => 'Review বাকি', 'approved' => 'Catalog review অনুমোদিত', default => 'প্রত্যাখ্যাত' }) ?></span></div>
                        <p><?= e($submission['business_name']) ?> · <?= e($submission['email']) ?> · <?= e($submission['category']) ?></p>
                        <div class="seller-submission-preview"><img src="<?= e($submission['image']) ?>" alt=""><div><strong><?= taka((int) $submission['price']) ?></strong><?php if ($submission['old_price'] !== null): ?> · <s><?= taka((int) $submission['old_price']) ?></s><?php endif; ?><br>স্টক প্রস্তাব: <?= (int) $submission['stock_quantity'] ?>টি<p><?= nl2br(e($submission['description'])) ?></p></div></div>
                        <small>আপডেট: <?= e($submission['updated_at']) ?><?= $submission['reviewed_at'] !== null ? ' · Review: ' . e($submission['reviewed_at']) . ' · ' . e((string) $submission['reviewed_by']) : '' ?></small>
                        <?php if ($submission['status'] === 'submitted'): ?>
                            <form class="seller-review-form" method="post" action="admin.php?view=seller_products"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_seller_product"><input type="hidden" name="id" value="<?= (int) $submission['id'] ?>"><label>প্রশাসনিক নোট<textarea name="admin_notes" maxlength="1000" rows="2"></textarea></label><div class="admin-form-actions"><button class="button button-dark" type="submit" name="decision" value="approved">Catalog review অনুমোদন</button><button class="button button-quiet" type="submit" name="decision" value="rejected">প্রত্যাখ্যান</button></div></form>
                        <?php elseif ($submission['admin_notes'] !== ''): ?><p class="seller-admin-note">নোট: <?= e($submission['admin_notes']) ?></p><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php elseif ($view === 'reviews'): ?>
            <?php $reviews = db()->query('SELECT r.*, p.name AS product_name, o.order_number FROM product_reviews r JOIN products p ON p.id = r.product_id JOIN orders o ON o.id = r.order_id ORDER BY CASE r.status WHEN "pending" THEN 0 ELSE 1 END, r.id DESC')->fetchAll(); ?>
            <div class="admin-page-title"><div><span class="eyebrow">CUSTOMER VOICE</span><h1>পণ্য রিভিউ</h1><p>যাচাইকৃত ক্রেতার মতামত অনুমোদন বা প্রত্যাখ্যান করুন।</p></div></div>
            <?php if ($reviews === []): ?><section class="admin-panel admin-empty">এখনো কোনো রিভিউ জমা পড়েনি।</section><?php endif; ?>
            <div class="admin-reviews"><?php foreach ($reviews as $review): ?><article class="admin-panel admin-review-item"><div class="review-card-heading"><strong><?= e($review['customer_name']) ?> · <?= e($review['product_name']) ?></strong><span><?= str_repeat('★', (int) $review['rating']) . str_repeat('☆', 5 - (int) $review['rating']) ?></span></div><p><?= nl2br(e($review['comment'])) ?></p><small><?= e($review['order_number']) ?> · <?= e($review['created_at']) ?> · <?= e(admin_status($review['status'])) ?></small><form method="post" action="admin.php?view=reviews"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="moderate_review"><input type="hidden" name="id" value="<?= (int) $review['id'] ?>"><button class="button button-dark" type="submit" name="status" value="approved">অনুমোদন</button><button class="button button-quiet" type="submit" name="status" value="rejected">প্রত্যাখ্যান</button></form></article><?php endforeach; ?></div>

        <?php elseif ($view === 'search_analytics'): ?>
            <?php
            $searchSummary = search_analytics_summary();
            $totalSearches = array_sum(array_column($searchSummary, 'search_count'));
            $totalNoResults = array_sum(array_column($searchSummary, 'no_result_count'));
            ?>
            <div class="admin-page-title"><div><span class="eyebrow">SMART DISCOVERY · LAST 7 DAYS</span><h1>সার্চ ডেটা</h1><p>জনপ্রিয় ও ফলাফলহীন search query দেখুন; visitor identity সংরক্ষণ করা হয় না।</p></div></div>
            <div class="search-analytics-metrics"><article><span>সার্চ (দৃশ্যমান query-সমূহ)</span><strong><?= $totalSearches ?></strong><small>গত ৭ দিনের aggregate</small></article><article><span>ফলাফলহীন সার্চ</span><strong><?= $totalNoResults ?></strong><small>query aggregate অনুযায়ী</small></article></div>
            <section class="admin-panel"><div class="admin-panel-heading"><h2>Search term ও ফল</h2><span>সর্বোচ্চ ১০০টি term</span></div>
                <?php if ($searchSummary === []): ?><div class="admin-empty">এখনো search data নেই।</div><?php else: ?>
                    <div class="table-wrap"><table><thead><tr><th>সার্চ শব্দ</th><th>সার্চ সংখ্যা</th><th>ফলাফলহীন</th><th>গড় ফলাফল</th></tr></thead><tbody>
                        <?php foreach ($searchSummary as $row): ?><tr><td><?= e($row['normalized_query']) ?></td><td><?= (int) $row['search_count'] ?></td><td><?= (int) $row['no_result_count'] ?></td><td><?= number_format((float) $row['average_results'], 1) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </section>
</main>
<?php page_footer(); ?>
