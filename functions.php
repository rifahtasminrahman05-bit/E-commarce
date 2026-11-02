<?php
declare(strict_types=1);

$envFile = __DIR__ . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) && getenv($key) === false) {
            if (strlen($value) > 1 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

$httpsRequest = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
$isProduction = (getenv('ALLSHOP_ENV') ?: 'development') === 'production';
$secureCookie = $httpsRequest || $isProduction || filter_var(getenv('ALLSHOP_SECURE_COOKIES') ?: 'false', FILTER_VALIDATE_BOOLEAN);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('allshop_session');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secureCookie,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; form-action 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'");
    if ($httpsRequest) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

if (isset($_SESSION['admin_user'])) {
    $now = time();
    $lastActivity = filter_var($_SESSION['admin_last_activity'] ?? null, FILTER_VALIDATE_INT);
    if ($lastActivity !== false && $lastActivity !== null && $now - $lastActivity > 1800) {
        unset($_SESSION['admin_user'], $_SESSION['admin_last_activity']);
        session_regenerate_id(true);
    } elseif ($lastActivity === false || $lastActivity === null) {
        $_SESSION['admin_last_activity'] = $now;
    } else {
        $_SESSION['admin_last_activity'] = $now;
    }
}

if (isset($_SESSION['seller_id'])) {
    $now = time();
    $lastActivity = filter_var($_SESSION['seller_last_activity'] ?? null, FILTER_VALIDATE_INT);
    if ($lastActivity === false || $lastActivity === null || $now - $lastActivity > 1800) {
        unset($_SESSION['seller_id'], $_SESSION['seller_last_activity']);
        session_regenerate_id(true);
    } else {
        $_SESSION['seller_last_activity'] = $now;
    }
}

function config(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

function production_configuration_errors(): array
{
    if (config('ALLSHOP_ENV', 'development') !== 'production') {
        return [];
    }

    $errors = [];
    $appUrl = config('ALLSHOP_APP_URL', '');
    $appUrlParts = is_string($appUrl) ? parse_url($appUrl) : false;
    if (
        !is_array($appUrlParts)
        || ($appUrlParts['scheme'] ?? null) !== 'https'
        || !isset($appUrlParts['host'])
        || in_array(strtolower($appUrlParts['host']), ['localhost', '127.0.0.1', '::1'], true)
    ) {
        $errors[] = 'ALLSHOP_APP_URL must be a public HTTPS URL.';
    }

    $appKey = (string) config('ALLSHOP_APP_KEY', '');
    if (strlen($appKey) < 32 || str_starts_with(strtolower($appKey), 'replace-with-')) {
        $errors[] = 'ALLSHOP_APP_KEY must contain at least 32 characters.';
    }
    $adminUsername = strtolower((string) config('ALLSHOP_ADMIN_USERNAME', ''));
    if ($adminUsername === '' || in_array($adminUsername, ['admin', 'administrator', 'change-this-admin-name', 'your-admin-username'], true)) {
        $errors[] = 'ALLSHOP_ADMIN_USERNAME must be configured.';
    }
    $adminPassword = (string) config('ALLSHOP_ADMIN_PASSWORD', '');
    if (strlen($adminPassword) < 20 || str_starts_with(strtolower($adminPassword), 'replace-with-')) {
        $errors[] = 'ALLSHOP_ADMIN_PASSWORD must contain at least 20 characters.';
    }
    if (!filter_var(config('ALLSHOP_SECURE_COOKIES', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        $errors[] = 'ALLSHOP_SECURE_COOKIES must be enabled.';
    }

    $databasePath = config('ALLSHOP_DB_PATH', '');
    if (!is_string($databasePath) || $databasePath === '' || $databasePath[0] !== '/') {
        $errors[] = 'ALLSHOP_DB_PATH must be an absolute path outside the public application directory.';
    } else {
        $databaseDirectory = realpath(dirname($databasePath));
        $applicationDirectory = realpath(__DIR__);
        $canonicalDatabasePath = realpath($databasePath)
            ?: ($databaseDirectory === false ? false : $databaseDirectory . DIRECTORY_SEPARATOR . basename($databasePath));
        if (
            $databaseDirectory === false
            || $canonicalDatabasePath === false
            || $applicationDirectory === false
            || $canonicalDatabasePath === $applicationDirectory
            || str_starts_with($canonicalDatabasePath, $applicationDirectory . DIRECTORY_SEPARATOR)
            || is_dir($canonicalDatabasePath)
        ) {
            $errors[] = 'ALLSHOP_DB_PATH must use a pre-created directory outside the public application directory.';
        } elseif (!is_dir($databaseDirectory) || !is_writable($databaseDirectory) || (is_file($canonicalDatabasePath) && !is_writable($canonicalDatabasePath))) {
            $errors[] = 'ALLSHOP_DB_PATH and its parent directory must be writable by the application runtime.';
        }
    }

    $envFile = __DIR__ . '/.env';
    if (is_file($envFile)) {
        $permissions = fileperms($envFile);
        if ($permissions !== false && ($permissions & 0077) !== 0) {
            $errors[] = 'The .env file must not be readable or writable by group or other users.';
        }
    }

    return $errors;
}

if (PHP_SAPI !== 'cli') {
    $productionErrors = production_configuration_errors();
    if ($productionErrors !== []) {
        error_log('All Shop production configuration is invalid: ' . implode(' ', $productionErrors));
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Store temporarily unavailable. Please contact the site administrator.');
    }
}

function admin_login_attempt_key(string $username): string
{
    $clientAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash_hmac('sha256', strtolower(trim($username)) . "\0" . $clientAddress, (string) config('ALLSHOP_APP_KEY', __DIR__));
}

function admin_login_is_blocked(string $username): bool
{
    $statement = db()->prepare('SELECT blocked_until FROM admin_login_attempts WHERE attempt_key = :key');
    $statement->execute(['key' => admin_login_attempt_key($username)]);
    $blockedUntil = $statement->fetchColumn();
    return $blockedUntil !== false && (int) $blockedUntil > time();
}

function record_admin_login_failure(string $username): void
{
    $statement = db()->prepare(
        'INSERT INTO admin_login_attempts (attempt_key, failed_count, window_started_at, blocked_until)
         VALUES (:key, 1, CAST(strftime("%s", "now") AS INTEGER), 0)
         ON CONFLICT(attempt_key) DO UPDATE SET
           failed_count = CASE
             WHEN CAST(strftime("%s", "now") AS INTEGER) - window_started_at >= 900 THEN 1
             ELSE failed_count + 1
           END,
           window_started_at = CASE
             WHEN CAST(strftime("%s", "now") AS INTEGER) - window_started_at >= 900
             THEN CAST(strftime("%s", "now") AS INTEGER)
             ELSE window_started_at
           END,
           blocked_until = CASE
             WHEN CASE
               WHEN CAST(strftime("%s", "now") AS INTEGER) - window_started_at >= 900 THEN 1
               ELSE failed_count + 1
             END >= 5 THEN CAST(strftime("%s", "now") AS INTEGER) + 900
             ELSE blocked_until
           END'
    );
    $statement->execute(['key' => admin_login_attempt_key($username)]);
    db()->prepare(
        'DELETE FROM admin_login_attempts
         WHERE window_started_at < :cutoff AND blocked_until < :now'
    )->execute(['cutoff' => time() - 86400, 'now' => time()]);
}

function clear_admin_login_failures(string $username): void
{
    db()->prepare('DELETE FROM admin_login_attempts WHERE attempt_key = :key')
        ->execute(['key' => admin_login_attempt_key($username)]);
}

function seller_login_attempt_key(string $email): string
{
    $clientAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash_hmac('sha256', "seller\0" . strtolower(trim($email)) . "\0" . $clientAddress, (string) config('ALLSHOP_APP_KEY', __DIR__));
}

function seller_login_is_blocked(string $email): bool
{
    $statement = db()->prepare('SELECT blocked_until FROM seller_login_attempts WHERE attempt_key = :key');
    $statement->execute(['key' => seller_login_attempt_key($email)]);
    $blockedUntil = $statement->fetchColumn();
    return $blockedUntil !== false && (int) $blockedUntil > time();
}

function record_seller_login_failure(string $email): void
{
    $key = seller_login_attempt_key($email);
    db()->prepare(
        'INSERT INTO seller_login_attempts (attempt_key, failed_count, window_started_at, blocked_until)
         VALUES (:key, 1, CAST(strftime("%s", "now") AS INTEGER), 0)
         ON CONFLICT(attempt_key) DO UPDATE SET
           failed_count = CASE
             WHEN CAST(strftime("%s", "now") AS INTEGER) - window_started_at >= 900 THEN 1
             ELSE failed_count + 1
           END,
           window_started_at = CASE
             WHEN CAST(strftime("%s", "now") AS INTEGER) - window_started_at >= 900
             THEN CAST(strftime("%s", "now") AS INTEGER)
             ELSE window_started_at
           END,
           blocked_until = CASE
             WHEN CASE
               WHEN CAST(strftime("%s", "now") AS INTEGER) - window_started_at >= 900 THEN 1
               ELSE failed_count + 1
             END >= 5 THEN CAST(strftime("%s", "now") AS INTEGER) + 900
             ELSE blocked_until
           END'
    )->execute(['key' => $key]);
    db()->prepare(
        'DELETE FROM seller_login_attempts
         WHERE window_started_at < :cutoff AND blocked_until < :now'
    )->execute(['cutoff' => time() - 86400, 'now' => time()]);
}

function clear_seller_login_failures(string $email): void
{
    db()->prepare('DELETE FROM seller_login_attempts WHERE attempt_key = :key')
        ->execute(['key' => seller_login_attempt_key($email)]);
}

function seller_application_attempt_key(): string
{
    $clientAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash_hmac('sha256', "seller-application\0" . $clientAddress, (string) config('ALLSHOP_APP_KEY', __DIR__));
}

function seller_application_is_rate_limited(): bool
{
    $statement = db()->prepare('SELECT blocked_until FROM seller_application_attempts WHERE attempt_key = :key');
    $statement->execute(['key' => seller_application_attempt_key()]);
    $blockedUntil = $statement->fetchColumn();
    return $blockedUntil !== false && (int) $blockedUntil > time();
}

function record_seller_application_attempt(): void
{
    $key = seller_application_attempt_key();
    db()->prepare(
        'INSERT INTO seller_application_attempts (attempt_key, submission_count, window_started_at, blocked_until)
         VALUES (:key, 1, CAST(strftime("%s", "now") AS INTEGER), 0)
         ON CONFLICT(attempt_key) DO UPDATE SET
           submission_count = CASE
             WHEN CAST(strftime("%s", "now") AS INTEGER) - window_started_at >= 3600 THEN 1
             ELSE submission_count + 1
           END,
           window_started_at = CASE
             WHEN CAST(strftime("%s", "now") AS INTEGER) - window_started_at >= 3600
             THEN CAST(strftime("%s", "now") AS INTEGER)
             ELSE window_started_at
           END,
           blocked_until = CASE
             WHEN CASE
               WHEN CAST(strftime("%s", "now") AS INTEGER) - window_started_at >= 3600 THEN 1
               ELSE submission_count + 1
             END >= 3 THEN CAST(strftime("%s", "now") AS INTEGER) + 3600
             ELSE blocked_until
           END'
    )->execute(['key' => $key]);
    db()->prepare(
        'DELETE FROM seller_application_attempts
         WHERE window_started_at < :cutoff AND blocked_until < :now'
    )->execute(['cutoff' => time() - 86400, 'now' => time()]);
}

function current_seller(): ?array
{
    $id = filter_var($_SESSION['seller_id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id < 1) {
        return null;
    }

    $statement = db()->prepare('SELECT id, business_name, owner_name, email, status FROM sellers WHERE id = :id');
    $statement->execute(['id' => $id]);
    $seller = $statement->fetch();
    if ($seller === false || $seller['status'] !== 'active') {
        unset($_SESSION['seller_id'], $_SESSION['seller_last_activity']);
        return null;
    }

    $_SESSION['seller_last_activity'] = time();
    return $seller;
}

function require_seller(): array
{
    $seller = current_seller();
    if ($seller === null) {
        redirect('seller_login.php');
    }
    return $seller;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = config('ALLSHOP_DB_PATH', __DIR__ . '/storage/allshop.sqlite');
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the SQLite storage directory.');
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            category TEXT NOT NULL,
            price INTEGER NOT NULL CHECK (price >= 0),
            old_price INTEGER NULL CHECK (old_price IS NULL OR old_price >= 0),
            badge TEXT NOT NULL DEFAULT "",
            image TEXT NOT NULL,
            description TEXT NOT NULL,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number TEXT NOT NULL UNIQUE,
            callback_hash TEXT NULL,
            customer_name TEXT NOT NULL,
            phone TEXT NOT NULL,
            address TEXT NOT NULL,
            payment_method TEXT NOT NULL,
            payment_status TEXT NOT NULL DEFAULT "unpaid",
            payment_id TEXT NULL,
            transaction_id TEXT NULL,
            order_status TEXT NOT NULL DEFAULT "pending",
            subtotal INTEGER NOT NULL,
            delivery INTEGER NOT NULL,
            total INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
            product_id INTEGER NULL,
            product_name TEXT NOT NULL,
            quantity INTEGER NOT NULL CHECK (quantity > 0),
            unit_price INTEGER NOT NULL CHECK (unit_price >= 0)
        );
        CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS contact_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT NOT NULL DEFAULT "",
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "new" CHECK (status IN ("new", "in_progress", "closed")),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS admin_login_attempts (
            attempt_key TEXT PRIMARY KEY,
            failed_count INTEGER NOT NULL DEFAULT 0,
            window_started_at INTEGER NOT NULL,
            blocked_until INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS seller_applications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            business_name TEXT NOT NULL,
            owner_name TEXT NOT NULL,
            email TEXT NOT NULL COLLATE NOCASE UNIQUE,
            phone TEXT NOT NULL,
            business_address TEXT NOT NULL,
            categories TEXT NOT NULL,
            website TEXT NOT NULL DEFAULT "",
            application_note TEXT NOT NULL DEFAULT "",
            password_hash TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "pending" CHECK (status IN ("pending", "approved", "rejected")),
            business_verified INTEGER NOT NULL DEFAULT 0 CHECK (business_verified IN (0, 1)),
            phone_verified INTEGER NOT NULL DEFAULT 0 CHECK (phone_verified IN (0, 1)),
            payout_verified INTEGER NOT NULL DEFAULT 0 CHECK (payout_verified IN (0, 1)),
            legal_tax_verified INTEGER NOT NULL DEFAULT 0 CHECK (legal_tax_verified IN (0, 1)),
            admin_notes TEXT NOT NULL DEFAULT "",
            reviewed_by TEXT NULL,
            reviewed_at TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS sellers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            application_id INTEGER NOT NULL UNIQUE REFERENCES seller_applications(id) ON DELETE RESTRICT,
            business_name TEXT NOT NULL,
            owner_name TEXT NOT NULL,
            email TEXT NOT NULL COLLATE NOCASE UNIQUE,
            phone TEXT NOT NULL,
            business_address TEXT NOT NULL,
            categories TEXT NOT NULL,
            website TEXT NOT NULL DEFAULT "",
            password_hash TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "active" CHECK (status IN ("active", "suspended")),
            last_login_at TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS seller_login_attempts (
            attempt_key TEXT PRIMARY KEY,
            failed_count INTEGER NOT NULL DEFAULT 0,
            window_started_at INTEGER NOT NULL,
            blocked_until INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS seller_application_attempts (
            attempt_key TEXT PRIMARY KEY,
            submission_count INTEGER NOT NULL DEFAULT 0,
            window_started_at INTEGER NOT NULL,
            blocked_until INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS seller_product_submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            seller_id INTEGER NOT NULL REFERENCES sellers(id) ON DELETE RESTRICT,
            name TEXT NOT NULL,
            category TEXT NOT NULL,
            price INTEGER NOT NULL CHECK (price >= 0),
            old_price INTEGER NULL CHECK (old_price IS NULL OR old_price >= 0),
            image TEXT NOT NULL,
            description TEXT NOT NULL,
            stock_quantity INTEGER NOT NULL DEFAULT 0 CHECK (stock_quantity >= 0),
            status TEXT NOT NULL DEFAULT "draft" CHECK (status IN ("draft", "submitted", "approved", "rejected")),
            admin_notes TEXT NOT NULL DEFAULT "",
            reviewed_by TEXT NULL,
            reviewed_at TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS app_settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            discount_type TEXT NOT NULL CHECK (discount_type IN ("percent", "fixed")),
            discount_value INTEGER NOT NULL CHECK (discount_value > 0),
            minimum_order INTEGER NOT NULL DEFAULT 0 CHECK (minimum_order >= 0),
            maximum_discount INTEGER NULL CHECK (maximum_discount IS NULL OR maximum_discount >= 0),
            usage_limit INTEGER NULL CHECK (usage_limit IS NULL OR usage_limit > 0),
            usage_count INTEGER NOT NULL DEFAULT 0 CHECK (usage_count >= 0),
            starts_at TEXT NULL,
            ends_at TEXT NULL,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS product_variants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            label TEXT NOT NULL,
            stock_quantity INTEGER NOT NULL DEFAULT 0 CHECK (stock_quantity >= 0),
            active INTEGER NOT NULL DEFAULT 1,
            UNIQUE (product_id, label)
        );
        CREATE TABLE IF NOT EXISTS product_search_terms (
            product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            term TEXT NOT NULL,
            normalized_term TEXT NOT NULL,
            PRIMARY KEY (product_id, normalized_term)
        );
        CREATE TABLE IF NOT EXISTS search_analytics (
            bucket_hour TEXT NOT NULL,
            normalized_query TEXT NOT NULL,
            search_count INTEGER NOT NULL DEFAULT 0 CHECK (search_count >= 0),
            no_result_count INTEGER NOT NULL DEFAULT 0 CHECK (no_result_count >= 0),
            result_count_total INTEGER NOT NULL DEFAULT 0 CHECK (result_count_total >= 0),
            PRIMARY KEY (bucket_hour, normalized_query)
        );
        CREATE TABLE IF NOT EXISTS product_reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL REFERENCES products(id),
            order_id INTEGER NOT NULL REFERENCES orders(id),
            customer_name TEXT NOT NULL,
            rating INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
            comment TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "pending" CHECK (status IN ("pending", "approved", "rejected")),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (product_id, order_id)
        );
        CREATE INDEX IF NOT EXISTS idx_orders_created_at ON orders(created_at);
        CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(order_status, payment_status);
        CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);
        CREATE INDEX IF NOT EXISTS idx_contact_messages_status_created ON contact_messages(status, created_at);
        CREATE INDEX IF NOT EXISTS idx_product_variants_product_active ON product_variants(product_id, active);
        CREATE INDEX IF NOT EXISTS idx_product_search_terms_normalized ON product_search_terms(normalized_term);
        CREATE INDEX IF NOT EXISTS idx_search_analytics_bucket ON search_analytics(bucket_hour);
        CREATE INDEX IF NOT EXISTS idx_seller_applications_status_created ON seller_applications(status, created_at);
        CREATE INDEX IF NOT EXISTS idx_seller_submissions_seller_status ON seller_product_submissions(seller_id, status, updated_at);'
    );
    $existingProductColumns = $pdo->query('PRAGMA table_info(products)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('track_inventory', $existingProductColumns, true)) {
        $pdo->exec('ALTER TABLE products ADD COLUMN track_inventory INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('stock_quantity', $existingProductColumns, true)) {
        $pdo->exec('ALTER TABLE products ADD COLUMN stock_quantity INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('low_stock_threshold', $existingProductColumns, true)) {
        $pdo->exec('ALTER TABLE products ADD COLUMN low_stock_threshold INTEGER NOT NULL DEFAULT 5');
    }
    $existingOrderColumns = $pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('coupon_code', $existingOrderColumns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN coupon_code TEXT NULL');
    }
    if (!in_array('discount', $existingOrderColumns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN discount INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('coupon_released', $existingOrderColumns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN coupon_released INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('stock_released', $existingOrderColumns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN stock_released INTEGER NOT NULL DEFAULT 0');
    }
    $existingOrderItemColumns = $pdo->query('PRAGMA table_info(order_items)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('variant_id', $existingOrderItemColumns, true)) {
        $pdo->exec('ALTER TABLE order_items ADD COLUMN variant_id INTEGER NULL REFERENCES product_variants(id) ON DELETE SET NULL');
    }
    if (!in_array('variant_label', $existingOrderItemColumns, true)) {
        $pdo->exec('ALTER TABLE order_items ADD COLUMN variant_label TEXT NULL');
    }
    if (!in_array('stock_reserved', $existingOrderItemColumns, true)) {
        $pdo->exec('ALTER TABLE order_items ADD COLUMN stock_reserved INTEGER NOT NULL DEFAULT 0');
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_product_reviews_product_status ON product_reviews(product_id, status)');

    if ((int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        $insert = $pdo->prepare(
            'INSERT INTO products (name, category, price, old_price, badge, image, description, track_inventory, stock_quantity)
             VALUES (:name, :category, :price, :old_price, :badge, :image, :description, 1, 20)'
        );
        foreach (initial_products() as $product) {
            $insert->execute($product);
        }
    }

    $coupon = $pdo->prepare(
        'INSERT OR IGNORE INTO coupons (code, discount_type, discount_value, minimum_order, maximum_discount)
         VALUES ("ALLSHOP10", "percent", 10, 1000, 500)'
    );
    $coupon->execute();

    $adminPassword = config('ALLSHOP_ADMIN_PASSWORD');
    if ($adminPassword !== null && (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() === 0) {
        $statement = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :password)');
        $statement->execute([
            'username' => config('ALLSHOP_ADMIN_USERNAME', 'admin'),
            'password' => password_hash($adminPassword, PASSWORD_DEFAULT),
        ]);
    }

    $pdo->exec("DELETE FROM search_analytics WHERE bucket_hour < datetime('now', '-7 days')");

    return $pdo;
}

function initial_products(): array
{
    return [
        ['name' => 'Minimal Ceramic Mug', 'category' => 'Home', 'price' => 420, 'old_price' => 550, 'badge' => 'BESTSELLER', 'image' => 'https://images.unsplash.com/photo-1514228742587-6b1558fcca3d?auto=format&fit=crop&w=900&q=85', 'description' => 'প্রতিদিনের কফি বা চায়ের জন্য সুন্দর, হাতে তৈরি সিরামিক মগ।'],
        ['name' => 'Everyday Tote Bag', 'category' => 'Fashion', 'price' => 690, 'old_price' => null, 'badge' => 'NEW', 'image' => 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?auto=format&fit=crop&w=900&q=85', 'description' => 'হালকা ও টেকসই ক্যানভাস টোট—প্রতিদিনের প্রয়োজনীয় জিনিস সঙ্গে রাখুন।'],
        ['name' => 'Wireless Headphones', 'category' => 'Electronics', 'price' => 2450, 'old_price' => 2990, 'badge' => 'SALE', 'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=85', 'description' => 'আরামদায়ক ডিজাইন, পরিষ্কার সাউন্ড এবং সারাদিনের ব্যাটারি।'],
        ['name' => 'Daily Care Set', 'category' => 'Beauty', 'price' => 980, 'old_price' => null, 'badge' => '', 'image' => 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?auto=format&fit=crop&w=900&q=85', 'description' => 'আপনার দৈনন্দিন যত্নের রুটিনের জন্য বাছাই করা স্কিনকেয়ার সেট।'],
        ['name' => 'Classic Wrist Watch', 'category' => 'Fashion', 'price' => 1890, 'old_price' => 2290, 'badge' => 'POPULAR', 'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=85', 'description' => 'সিম্পল ডায়াল আর আরামদায়ক স্ট্র্যাপে ক্লাসিক ঘড়ি।'],
        ['name' => 'Soft Knit Cushion', 'category' => 'Home', 'price' => 760, 'old_price' => null, 'badge' => '', 'image' => 'https://images.unsplash.com/photo-1584100936595-c0654b55a2e2?auto=format&fit=crop&w=900&q=85', 'description' => 'ঘরের সাজে আরাম আর উষ্ণতা যোগ করার নরম কুশন কভার।'],
        ['name' => 'Bluetooth Speaker', 'category' => 'Electronics', 'price' => 1650, 'old_price' => 1990, 'badge' => 'SALE', 'image' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=900&q=85', 'description' => 'কমপ্যাক্ট ডিজাইনে শক্তিশালী সাউন্ড—ঘরে বা বাইরে ব্যবহার করুন।'],
        ['name' => 'Everyday Face Serum', 'category' => 'Beauty', 'price' => 1150, 'old_price' => null, 'badge' => 'NEW', 'image' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=900&q=85', 'description' => 'হালকা, দ্রুত শোষিত হয়—সহজ স্কিনকেয়ার রুটিনের জন্য।'],
    ];
}

function normalize_search_text(string $value): string
{
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = str_replace(["\u{200c}", "\u{200d}", "\u{feff}", 'ড়', 'ঢ়'], ['', '', '', 'ড়', 'ঢ়'], $value);
    $value = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function search_term_variants(string $term): array
{
    $groups = [
        ['bag', 'bags', 'ব্যাগ', 'টোট', 'tote', 'handbag'],
        ['mug', 'মগ', 'cup', 'কাপ'],
        ['headphone', 'headphones', 'headset', 'হেডফোন'],
        ['watch', 'wristwatch', 'ঘড়ি', 'ঘড়ি', 'ghori'],
        ['speaker', 'স্পিকার'],
        ['serum', 'সেরাম', 'সিরাম'],
        ['skincare', 'skin', 'স্কিনকেয়ার', 'ত্বক'],
    ];
    $variants = [$term];
    foreach ($groups as $group) {
        if (in_array($term, $group, true)) {
            $variants = array_merge($variants, $group);
            break;
        }
    }

    return array_values(array_unique(array_filter(array_map('normalize_search_text', $variants))));
}

function product_search_terms(int $productId): array
{
    $statement = db()->prepare(
        'SELECT term FROM product_search_terms WHERE product_id = :product_id ORDER BY normalized_term'
    );
    $statement->execute(['product_id' => $productId]);
    return array_column($statement->fetchAll(), 'term');
}

function save_product_search_terms(int $productId, array $terms): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM product_search_terms WHERE product_id = :product_id')
        ->execute(['product_id' => $productId]);
    $statement = $pdo->prepare(
        'INSERT INTO product_search_terms (product_id, term, normalized_term)
         VALUES (:product_id, :term, :normalized_term)'
    );
    foreach ($terms as $term) {
        $normalized = normalize_search_text($term);
        if ($normalized === '') {
            continue;
        }
        $statement->execute([
            'product_id' => $productId,
            'term' => $term,
            'normalized_term' => $normalized,
        ]);
    }
}

function product_search_suggestions(): array
{
    $statement = db()->query(
        'SELECT term FROM (
            SELECT name AS term FROM products WHERE active = 1
            UNION SELECT category AS term FROM products WHERE active = 1
            UNION SELECT search_term.term AS term
                  FROM product_search_terms search_term
                  JOIN products p ON p.id = search_term.product_id
                  WHERE p.active = 1
         )
         ORDER BY term COLLATE NOCASE
         LIMIT 80'
    );
    return array_column($statement->fetchAll(), 'term');
}

function record_search_analytics(string $query, int $resultCount): void
{
    $digits = preg_replace('/[^\p{N}]/u', '', $query) ?? '';
    if ($query === '' || text_length($query) > 80 || str_contains($query, '@') || text_length($digits) >= 7) {
        return;
    }

    $normalizedQuery = normalize_search_text($query);
    if ($normalizedQuery === '') {
        return;
    }

    $statement = db()->prepare(
        'INSERT INTO search_analytics (
            bucket_hour, normalized_query, search_count, no_result_count, result_count_total
         )
         VALUES (strftime("%Y-%m-%d %H:00:00", "now"), :query, 1, :no_result, :result_count)
         ON CONFLICT(bucket_hour, normalized_query) DO UPDATE SET
            search_count = search_count + 1,
            no_result_count = no_result_count + excluded.no_result_count,
            result_count_total = result_count_total + excluded.result_count_total'
    );
    $statement->execute([
        'query' => $normalizedQuery,
        'no_result' => $resultCount === 0 ? 1 : 0,
        'result_count' => max(0, $resultCount),
    ]);
}

function search_analytics_summary(): array
{
    return db()->query(
        'SELECT normalized_query,
                SUM(search_count) AS search_count,
                SUM(no_result_count) AS no_result_count,
                ROUND(SUM(result_count_total) * 1.0 / SUM(search_count), 1) AS average_results
         FROM search_analytics
         WHERE bucket_hour >= datetime("now", "-7 days")
         GROUP BY normalized_query
         ORDER BY no_result_count DESC, search_count DESC, normalized_query COLLATE NOCASE
         LIMIT 100'
    )->fetchAll();
}

function products(?string $category = null, string $search = ''): array
{
    $normalizedSearch = normalize_search_text($search);
    if ($search !== '' && $normalizedSearch === '') {
        return [];
    }

    $sql = 'SELECT p.id, p.name, p.category, p.price, p.old_price, p.badge, p.image, p.description,
                   p.track_inventory, p.stock_quantity, p.low_stock_threshold,
                   EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.active = 1) AS has_variants,
                   (SELECT COALESCE(SUM(v.stock_quantity), 0) FROM product_variants v WHERE v.product_id = p.id AND v.active = 1) AS variant_stock,
                   EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.active = 1 AND v.stock_quantity <= p.low_stock_threshold) AS has_low_stock,
                   COALESCE(search_terms.terms, "") AS search_terms
            FROM products p
            LEFT JOIN (
                SELECT product_id, GROUP_CONCAT(normalized_term, " ") AS terms
                FROM product_search_terms GROUP BY product_id
            ) search_terms ON search_terms.product_id = p.id
            WHERE p.active = 1';
    $parameters = [];
    if ($category !== null && $category !== '') {
        $sql .= ' AND category = :category';
        $parameters['category'] = $category;
    }
    if ($search !== '') {
        $tokens = array_slice(array_values(array_filter(explode(' ', $normalizedSearch))), 0, 8);
        foreach ($tokens as $tokenIndex => $token) {
            $conditions = [];
            foreach (search_term_variants($token) as $variantIndex => $variant) {
                $parameter = 'search_' . $tokenIndex . '_' . $variantIndex;
                $conditions[] = 'p.name LIKE :' . $parameter . ' ESCAPE \'#\'';
                $conditions[] = 'p.category LIKE :' . $parameter . ' ESCAPE \'#\'';
                $conditions[] = 'p.description LIKE :' . $parameter . ' ESCAPE \'#\'';
                $conditions[] = 'search_terms.terms LIKE :' . $parameter . ' ESCAPE \'#\'';
                $escapedVariant = strtr($variant, ['#' => '##', '%' => '#%', '_' => '#_']);
                $parameters[$parameter] = '%' . $escapedVariant . '%';
            }
            if ($conditions !== []) {
                $sql .= ' AND (' . implode(' OR ', $conditions) . ')';
            }
        }
    }
    $sql .= ' ORDER BY id ASC';
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    $result = [];
    $searchScores = [];
    foreach ($statement->fetchAll() as $product) {
        $normalizedName = normalize_search_text((string) $product['name']);
        $normalizedCategory = normalize_search_text((string) $product['category']);
        $normalizedDescription = normalize_search_text((string) $product['description']);
        $normalizedAliases = (string) $product['search_terms'];
        $searchScore = 0;
        if ($search !== '') {
            $normalizedSearch = normalize_search_text($search);
            if ($normalizedName === $normalizedSearch) {
                $searchScore += 100;
            } elseif (str_starts_with($normalizedName, $normalizedSearch)) {
                $searchScore += 50;
            } elseif (str_contains($normalizedName, $normalizedSearch)) {
                $searchScore += 35;
            }
            foreach (array_slice(array_values(array_filter(explode(' ', $normalizedSearch))), 0, 8) as $token) {
                $tokenScore = 0;
                foreach (search_term_variants($token) as $variant) {
                    if (str_contains($normalizedName, $variant)) {
                        $tokenScore = max($tokenScore, 15);
                    }
                    if (str_contains($normalizedCategory, $variant)) {
                        $tokenScore = max($tokenScore, 10);
                    }
                    if (str_contains($normalizedAliases, $variant)) {
                        $tokenScore = max($tokenScore, 20);
                    }
                    if (str_contains($normalizedDescription, $variant)) {
                        $tokenScore = max($tokenScore, 3);
                    }
                }
                $searchScore += $tokenScore;
            }
        }
        $product['id'] = (int) $product['id'];
        $product['price'] = (int) $product['price'];
        $product['old_price'] = $product['old_price'] === null ? null : (int) $product['old_price'];
        $product['track_inventory'] = (int) $product['track_inventory'];
        $product['stock_quantity'] = (int) $product['stock_quantity'];
        $product['low_stock_threshold'] = (int) $product['low_stock_threshold'];
        $product['has_variants'] = (bool) $product['has_variants'];
        $product['variant_stock'] = (int) $product['variant_stock'];
        $product['has_low_stock'] = (bool) $product['has_low_stock']
            || (!$product['has_variants'] && $product['track_inventory'] === 1 && $product['stock_quantity'] <= $product['low_stock_threshold']);
        unset($product['search_terms']);
        $result[$product['id']] = $product;
        $searchScores[$product['id']] = $searchScore;
    }

    if ($search !== '') {
        uasort($result, static fn(array $left, array $right): int =>
            ($searchScores[$right['id']] <=> $searchScores[$left['id']])
            ?: ($left['id'] <=> $right['id'])
        );
    }

    return $result;
}

function category_label(string $category): string
{
    return [
        'Beauty' => 'রূপচর্চা',
        'Electronics' => 'ইলেকট্রনিক্স',
        'Fashion' => 'ফ্যাশন',
        'Home' => 'ঘর ও সাজ',
    ][$category] ?? $category;
}

function product_by_id(int $id, bool $includeInactive = false): ?array
{
    $sql = 'SELECT p.id, p.name, p.category, p.price, p.old_price, p.badge, p.image, p.description,
                   p.active, p.track_inventory, p.stock_quantity, p.low_stock_threshold,
                   EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.active = 1) AS has_variants,
                   (SELECT COALESCE(SUM(v.stock_quantity), 0) FROM product_variants v WHERE v.product_id = p.id AND v.active = 1) AS variant_stock,
                   EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.active = 1 AND v.stock_quantity <= p.low_stock_threshold) AS has_low_stock
            FROM products p WHERE p.id = :id';
    if (!$includeInactive) {
        $sql .= ' AND active = 1';
    }
    $statement = db()->prepare($sql);
    $statement->execute(['id' => $id]);
    $product = $statement->fetch();
    if (!$product) {
        return null;
    }
    $product['id'] = (int) $product['id'];
    $product['price'] = (int) $product['price'];
    $product['old_price'] = $product['old_price'] === null ? null : (int) $product['old_price'];
    $product['track_inventory'] = (int) $product['track_inventory'];
    $product['stock_quantity'] = (int) $product['stock_quantity'];
    $product['low_stock_threshold'] = (int) $product['low_stock_threshold'];
    $product['has_variants'] = (bool) $product['has_variants'];
    $product['variant_stock'] = (int) $product['variant_stock'];
    $product['has_low_stock'] = (bool) $product['has_low_stock']
        || (!$product['has_variants'] && $product['track_inventory'] === 1 && $product['stock_quantity'] <= $product['low_stock_threshold']);

    return $product;
}

function product_variants(int $productId, bool $includeInactive = false): array
{
    $sql = 'SELECT id, product_id, label, stock_quantity, active FROM product_variants WHERE product_id = :product_id';
    if (!$includeInactive) {
        $sql .= ' AND active = 1';
    }
    $sql .= ' ORDER BY id';
    $statement = db()->prepare($sql);
    $statement->execute(['product_id' => $productId]);
    $variants = $statement->fetchAll();
    foreach ($variants as &$variant) {
        $variant['id'] = (int) $variant['id'];
        $variant['product_id'] = (int) $variant['product_id'];
        $variant['stock_quantity'] = (int) $variant['stock_quantity'];
        $variant['active'] = (int) $variant['active'];
    }
    unset($variant);
    return $variants;
}

function product_available_stock(array $product): ?int
{
    if ($product['has_variants']) {
        return (int) $product['variant_stock'];
    }
    return (int) $product['track_inventory'] === 1 ? (int) $product['stock_quantity'] : null;
}

function product_has_low_stock(array $product): bool
{
    return (bool) $product['has_low_stock'];
}

function product_rating(int $productId): array
{
    $statement = db()->prepare(
        'SELECT COUNT(*) AS review_count, COALESCE(AVG(rating), 0) AS average_rating
         FROM product_reviews WHERE product_id = :id AND status = "approved"'
    );
    $statement->execute(['id' => $productId]);
    $rating = $statement->fetch();
    return ['count' => (int) $rating['review_count'], 'average' => (float) $rating['average_rating']];
}

function product_ratings(array $productIds): array
{
    $ids = array_values(array_unique(array_filter($productIds, static fn(mixed $id): bool => is_int($id) && $id > 0)));
    if ($ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = db()->prepare(
        'SELECT product_id, COUNT(*) AS review_count, AVG(rating) AS average_rating
         FROM product_reviews WHERE status = "approved" AND product_id IN (' . $placeholders . ')
         GROUP BY product_id'
    );
    $statement->execute($ids);
    $ratings = [];
    foreach ($statement->fetchAll() as $rating) {
        $ratings[(int) $rating['product_id']] = [
            'count' => (int) $rating['review_count'],
            'average' => (float) $rating['average_rating'],
        ];
    }
    return $ratings;
}

function approved_reviews(int $productId): array
{
    $statement = db()->prepare(
        'SELECT customer_name, rating, comment, created_at FROM product_reviews
         WHERE product_id = :id AND status = "approved" ORDER BY created_at DESC, id DESC'
    );
    $statement->execute(['id' => $productId]);
    return $statement->fetchAll();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function text_length(string $value): int
{
    $length = preg_match_all('/./us', $value);
    return $length === false ? PHP_INT_MAX : $length;
}

function taka(int|float $amount): string
{
    return '৳' . number_format((float) $amount, 0);
}

function cart_items(): array
{
    $items = [];
    foreach ($_SESSION['cart'] ?? [] as $key => $quantity) {
        $parsed = parse_cart_key($key);
        if ($parsed === null) {
            continue;
        }
        [$id, $variantId] = $parsed;
        $product = product_by_id($id);
        if ($product !== null && is_int($quantity) && $quantity > 0) {
            $variant = null;
            if ($variantId !== null) {
                foreach (product_variants($id, true) as $candidate) {
                    if ($candidate['id'] === $variantId) {
                        $variant = $candidate;
                        break;
                    }
                }
            }
            $product['cart_key'] = $key;
            $product['variant_id'] = $variantId;
            $product['variant_label'] = $variant['label'] ?? null;
            $product['quantity'] = min(99, $quantity);
            $product['stock_tracked'] = $variantId !== null || (int) $product['track_inventory'] === 1;
            $product['available_stock'] = $variantId !== null
                ? (($variant !== null && $variant['active'] === 1) ? $variant['stock_quantity'] : 0)
                : (!$product['has_variants'] ? (product_available_stock($product) ?? PHP_INT_MAX) : 0);
            $product['selection_available'] = $variantId !== null
                ? $variant !== null && $variant['active'] === 1
                : !$product['has_variants'];
            $product['in_stock'] = $product['selection_available'];
            if ($product['stock_tracked'] && $product['quantity'] > $product['available_stock']) {
                $product['in_stock'] = false;
            }
            $items[] = $product;
        }
    }

    return $items;
}

function cart_stock_available(): bool
{
    foreach (cart_items() as $item) {
        if (!$item['in_stock'] || ($item['stock_tracked'] && $item['quantity'] > $item['available_stock'])) {
            return false;
        }
    }
    return true;
}

function cart_count(): int
{
    return array_sum(array_column(cart_items(), 'quantity'));
}

function cart_subtotal(): int
{
    $total = 0;
    foreach (cart_items() as $item) {
        $total += $item['price'] * $item['quantity'];
    }

    return $total;
}

function cart_key(int $productId, ?int $variantId = null): string|int
{
    return $variantId === null ? $productId : $productId . ':' . $variantId;
}

function parse_cart_key(mixed $key): ?array
{
    if (is_int($key) && $key > 0) {
        return [$key, null];
    }
    if (is_string($key) && preg_match('/^[1-9][0-9]*$/', $key)) {
        return [(int) $key, null];
    }
    if (is_string($key) && preg_match('/^([1-9][0-9]*):([1-9][0-9]*)$/', $key, $matches)) {
        return [(int) $matches[1], (int) $matches[2]];
    }
    return null;
}

function add_to_cart(int $productId, int $quantity, ?int $variantId = null): ?string
{
    $product = product_by_id($productId);
    if ($product === null || $quantity < 1 || $quantity > 99) {
        return 'পণ্যটি পাওয়া যায়নি অথবা পরিমাণটি সঠিক নয়।';
    }
    if ($product['has_variants'] && $variantId === null) {
        return 'কার্টে যোগ করার আগে পণ্যের সাইজ/রং বেছে নিন।';
    }
    if (!$product['has_variants'] && $variantId !== null) {
        return 'পণ্যের এই ভ্যারিয়েন্টটি আর পাওয়া যাচ্ছে না।';
    }
    $variant = null;
    if ($variantId !== null) {
        foreach (product_variants($productId) as $candidate) {
            if ($candidate['id'] === $variantId) {
                $variant = $candidate;
                break;
            }
        }
        if ($variant === null) {
            return 'পণ্যের এই ভ্যারিয়েন্টটি আর পাওয়া যাচ্ছে না।';
        }
    }
    $stock = $variant === null ? product_available_stock($product) : $variant['stock_quantity'];
    $key = cart_key($productId, $variantId);
    $alreadyAdded = (int) ($_SESSION['cart'][$key] ?? 0);
    if ($stock !== null && $alreadyAdded + $quantity > $stock) {
        return $stock === 0
            ? 'এই পণ্যটি এখন স্টকে নেই।'
            : 'স্টকে মাত্র ' . $stock . 'টি আছে; কার্টের পরিমাণ কমিয়ে আবার চেষ্টা করুন।';
    }
    $_SESSION['cart'][$key] = min(99, $alreadyAdded + $quantity);
    return null;
}

function release_order_stock(int $orderId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'SELECT id, product_id, variant_id, quantity FROM order_items
             WHERE order_id = :order_id AND stock_reserved = 1'
        );
        $statement->execute(['order_id' => $orderId]);
        $items = $statement->fetchAll();
        $markItem = $pdo->prepare('UPDATE order_items SET stock_reserved = 0 WHERE id = :id AND stock_reserved = 1');
        $restoreVariant = $pdo->prepare('UPDATE product_variants SET stock_quantity = stock_quantity + :quantity WHERE id = :id');
        $restoreProduct = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity + :quantity WHERE id = :id');
        foreach ($items as $item) {
            $markItem->execute(['id' => $item['id']]);
            if ($markItem->rowCount() !== 1) {
                continue;
            }
            if ($item['variant_id'] !== null) {
                $restoreVariant->execute(['quantity' => $item['quantity'], 'id' => $item['variant_id']]);
                if ($item['product_id'] !== null) {
                    $restoreProduct->execute(['quantity' => $item['quantity'], 'id' => $item['product_id']]);
                }
            } elseif ($item['product_id'] !== null) {
                $restoreProduct->execute(['quantity' => $item['quantity'], 'id' => $item['product_id']]);
            }
        }
        $markOrder = $pdo->prepare('UPDATE orders SET stock_released = 1 WHERE id = :id');
        $markOrder->execute(['id' => $orderId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function active_coupon(string $code): ?array
{
    $statement = db()->prepare(
        'SELECT * FROM coupons
         WHERE code = :code AND active = 1
           AND (starts_at IS NULL OR starts_at <= CURRENT_TIMESTAMP)
           AND (ends_at IS NULL OR ends_at >= CURRENT_TIMESTAMP)
           AND (usage_limit IS NULL OR usage_count < usage_limit)'
    );
    $statement->execute(['code' => strtoupper(trim($code))]);
    $coupon = $statement->fetch();
    return $coupon ?: null;
}

function available_coupons(int $limit = 20): array
{
    $limit = max(1, min(50, $limit));
    $statement = db()->prepare(
        'SELECT code, discount_type, discount_value, minimum_order, maximum_discount, usage_limit, usage_count, ends_at
         FROM coupons
         WHERE active = 1
           AND (starts_at IS NULL OR starts_at <= CURRENT_TIMESTAMP)
           AND (ends_at IS NULL OR ends_at >= CURRENT_TIMESTAMP)
           AND (usage_limit IS NULL OR usage_count < usage_limit)
         ORDER BY CASE WHEN ends_at IS NULL THEN 1 ELSE 0 END, ends_at ASC, id DESC
         LIMIT :limit'
    );
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function release_coupon_reservation(int $orderId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'SELECT coupon_code, coupon_released, payment_status FROM orders WHERE id = :id'
        );
        $statement->execute(['id' => $orderId]);
        $order = $statement->fetch();
        if ($order && $order['coupon_code'] !== null && (int) $order['coupon_released'] === 0 && $order['payment_status'] !== 'paid') {
            $release = $pdo->prepare('UPDATE coupons SET usage_count = MAX(0, usage_count - 1) WHERE code = :code');
            $release->execute(['code' => $order['coupon_code']]);
            $mark = $pdo->prepare('UPDATE orders SET coupon_released = 1 WHERE id = :id AND coupon_released = 0');
            $mark->execute(['id' => $orderId]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function coupon_discount(array $coupon, int $subtotal): int
{
    if ($subtotal < (int) $coupon['minimum_order']) {
        return 0;
    }
    $discount = $coupon['discount_type'] === 'percent'
        ? (int) floor($subtotal * (int) $coupon['discount_value'] / 100)
        : (int) $coupon['discount_value'];
    if ($coupon['maximum_discount'] !== null) {
        $discount = min($discount, (int) $coupon['maximum_discount']);
    }
    return min($subtotal, max(0, $discount));
}

function cart_coupon(): ?array
{
    $code = $_SESSION['coupon_code'] ?? null;
    if (!is_string($code) || $code === '') {
        return null;
    }
    $coupon = active_coupon($code);
    if ($coupon === null || cart_subtotal() < (int) $coupon['minimum_order']) {
        unset($_SESSION['coupon_code']);
        return null;
    }
    $coupon['discount_amount'] = coupon_discount($coupon, cart_subtotal());
    return $coupon;
}

function wishlist_ids(): array
{
    $ids = $_SESSION['wishlist'] ?? [];
    return is_array($ids) ? array_values(array_unique(array_filter($ids, 'is_int'))) : [];
}

function comparison_ids(): array
{
    $ids = $_SESSION['comparison'] ?? [];
    if (!is_array($ids)) {
        return [];
    }

    $ids = array_values(array_unique(array_filter($ids, static fn(mixed $id): bool => is_int($id) && $id > 0)));
    return array_slice($ids, 0, 3);
}

function toggle_wishlist(int $productId): void
{
    $ids = wishlist_ids();
    if (in_array($productId, $ids, true)) {
        $_SESSION['wishlist'] = array_values(array_diff($ids, [$productId]));
        flash('wishlist', 'পণ্যটি পছন্দের তালিকা থেকে সরানো হয়েছে।');
        return;
    }
    if (product_by_id($productId) === null) {
        throw new InvalidArgumentException('Product is not available.');
    }
    $_SESSION['wishlist'][] = $productId;
    flash('wishlist', 'পণ্যটি আপনার পছন্দের তালিকায় যোগ হয়েছে।');
}

function delivery_fee(int $subtotal): int
{
    return $subtotal >= free_delivery_threshold() ? 0 : 80;
}

function free_delivery_threshold(): int
{
    return 1500;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function checkout_nonce(): string
{
    if (empty($_SESSION['checkout_nonce'])) {
        $_SESSION['checkout_nonce'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['checkout_nonce'];
}

function verify_csrf(): bool
{
    $submitted = $_POST['csrf_token'] ?? null;
    $stored = $_SESSION['csrf_token'] ?? null;
    return is_string($submitted) && is_string($stored) && hash_equals($stored, $submitted);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return is_string($value) ? $value : null;
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function page_header(string $title): void
{
    $count = cart_count();
    echo '<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="description" content="All Shop — প্রতিদিনের পছন্দের জিনিস, এক জায়গায়।"><meta name="theme-color" content="#214e3c"><title>' . e($title) . ' | All Shop</title>';
    echo '<link rel="icon" href="favicon.svg" type="image/svg+xml"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="style.css"></head><body>';
    echo '<div class="topbar">সারা দেশে ডেলিভারি · ৳১,৫০০ বা তার বেশি অর্ডারে ডেলিভারি ফ্রি</div>';
    echo '<header class="site-header"><a class="brand" href="index.php"><span class="brand-mark">a</span><span>all<span class="brand-light">shop</span><small>ভালো পছন্দ, সহজ কেনাকাটা</small></span></a>';
    echo '<nav class="main-nav" aria-label="মূল নেভিগেশন"><a href="index.php">হোম</a><a href="index.php#products">সব পণ্য</a><a href="index.php?category=Fashion#products">ফ্যাশন</a><a href="index.php?category=Home#products">হোম &amp; লিভিং</a><a href="offers.php">অফার</a><a href="help.php">সাহায্য</a><a href="wishlist.php">♡ পছন্দ</a></nav>';
    $comparisonCount = count(comparison_ids());
    echo '<div class="header-actions"><a class="compare-header-link" href="compare.php" aria-label="পণ্য তুলনা, ' . $comparisonCount . 'টি পণ্য">⇄ <span>' . $comparisonCount . '</span></a><a class="wishlist-header-link" href="wishlist.php" aria-label="পছন্দের তালিকা, ' . count(wishlist_ids()) . 'টি পণ্য">♡ <span>' . count(wishlist_ids()) . '</span></a><a class="cart-link" href="cart.php" aria-label="কার্ট, ' . $count . 'টি পণ্য"><span class="cart-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 1.9-1.4L22 8H6"/><circle cx="10" cy="21" r="1"/><circle cx="19" cy="21" r="1"/></svg></span><span>কার্ট</span><b>' . $count . '</b></a></div></header>';
}

function page_footer(): void
{
    echo '<footer class="footer"><div class="footer-brand"><a class="brand footer-logo" href="index.php"><span class="brand-mark">a</span><span>all<span class="brand-light">shop</span></span></a><p>দৈনন্দিন জীবনের সুন্দর সব পছন্দ—এখন এক ঠিকানায়।</p></div><div><h3>কেনাকাটা</h3><a href="index.php#products">সব পণ্য</a><a href="offers.php">চলতি অফার</a><a href="wishlist.php">পছন্দের তালিকা</a><a href="index.php?category=Fashion#products">ফ্যাশন</a><a href="index.php?category=Electronics#products">ইলেকট্রনিক্স</a></div><div><h3>সাহায্য</h3><a href="help.php">সাধারণ জিজ্ঞাসা</a><a href="contact.php">যোগাযোগ</a><a href="cart.php">আপনার কার্ট</a><a href="order.php">অর্ডার ট্র্যাক করুন</a><span>ডেলিভারি: ২–৫ কর্মদিবস</span></div><div class="copyright">© ' . date('Y') . ' All Shop · বাংলাদেশ <a href="seller_apply.php">সেলার আবেদন</a> <a href="seller_login.php">সেলার লগইন</a> <a href="admin.php?view=login">অ্যাডমিন</a></div></footer><script src="promo.js" defer></script></body></html>';
}

function admin_logged_in(): bool
{
    return isset($_SESSION['admin_user']) && is_string($_SESSION['admin_user']);
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        redirect('admin.php?view=login');
    }
}

function bkash_checkout_enabled(): bool
{
    $environment = config('ALLSHOP_BKASH_ENV', 'sandbox');
    if (!in_array($environment, ['sandbox', 'live'], true)) {
        return false;
    }
    if (config('ALLSHOP_ENV', 'development') === 'production' && $environment !== 'live') {
        return false;
    }

    foreach (['ALLSHOP_BKASH_APP_KEY', 'ALLSHOP_BKASH_APP_SECRET', 'ALLSHOP_BKASH_USERNAME', 'ALLSHOP_BKASH_PASSWORD', 'ALLSHOP_APP_URL'] as $key) {
        if (config($key) === null) {
            return false;
        }
    }

    $appUrl = parse_url((string) config('ALLSHOP_APP_URL'));
    if (!is_array($appUrl) || !isset($appUrl['scheme'], $appUrl['host']) || !in_array($appUrl['scheme'], ['http', 'https'], true)) {
        return false;
    }
    return $environment !== 'live' || $appUrl['scheme'] === 'https';
}

function bkash_configuration(): array
{
    $environment = config('ALLSHOP_BKASH_ENV', 'sandbox');
    $base = match ($environment) {
        'sandbox' => 'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout',
        'live' => 'https://tokenized.pay.bka.sh/v2/tokenized-checkout',
        default => throw new RuntimeException('ALLSHOP_BKASH_ENV must be sandbox or live.'),
    };
    $settings = [
        'base' => $base,
        'app_key' => config('ALLSHOP_BKASH_APP_KEY'),
        'app_secret' => config('ALLSHOP_BKASH_APP_SECRET'),
        'username' => config('ALLSHOP_BKASH_USERNAME'),
        'password' => config('ALLSHOP_BKASH_PASSWORD'),
        'app_url' => rtrim((string) config('ALLSHOP_APP_URL', ''), '/'),
    ];
    foreach (['app_key', 'app_secret', 'username', 'password', 'app_url'] as $required) {
        if ($settings[$required] === null || $settings[$required] === '') {
            throw new RuntimeException('bKash merchant settings are incomplete.');
        }
    }
    $url = parse_url($settings['app_url']);
    if (!is_array($url) || !isset($url['scheme'], $url['host']) || !in_array($url['scheme'], ['http', 'https'], true)) {
        throw new RuntimeException('ALLSHOP_APP_URL must be an absolute HTTP or HTTPS URL.');
    }
    if ($environment === 'live' && $url['scheme'] !== 'https') {
        throw new RuntimeException('Live bKash payments require an HTTPS storefront URL.');
    }

    return $settings;
}

function bkash_request(string $path, array $payload, bool $grantToken = false): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required for bKash payments.');
    }
    $settings = bkash_configuration();
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($grantToken) {
        $headers[] = 'username: ' . $settings['username'];
        $headers[] = 'password: ' . $settings['password'];
    } else {
        $token = bkash_access_token($settings);
        $headers[] = 'Authorization: ' . $token;
        $headers[] = 'X-App-Key: ' . $settings['app_key'];
    }

    $curl = curl_init($settings['base'] . $path);
    if ($curl === false) {
        throw new RuntimeException('Could not initialize the bKash request.');
    }
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    if (!is_string($body)) {
        throw new RuntimeException('bKash could not be reached: ' . $curlError);
    }
    $response = json_decode($body, true);
    if (!is_array($response)) {
        throw new RuntimeException('bKash returned an unreadable response.');
    }
    if ($status < 200 || $status >= 300 || (isset($response['statusCode']) && $response['statusCode'] !== '0000') || isset($response['externalCode'])) {
        $reason = (string) ($response['errorMessageEn'] ?? $response['statusMessage'] ?? 'Request was declined.');
        throw new RuntimeException('bKash request failed (' . $status . '): ' . $reason);
    }

    return $response;
}

function bkash_access_token(array $settings): string
{
    $query = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = "bkash_token"');
    $query->execute();
    $token = $query->fetchColumn();
    $expiryQuery = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = "bkash_token_expiry"');
    $expiryQuery->execute();
    $expiry = (int) $expiryQuery->fetchColumn();
    if (is_string($token) && $token !== '' && $expiry > time() + 60) {
        return $token;
    }

    $response = bkash_request('/auth/grant-token', [
        'app_key' => $settings['app_key'],
        'app_secret' => $settings['app_secret'],
    ], true);
    if (!isset($response['id_token']) || !is_string($response['id_token']) || $response['id_token'] === '') {
        throw new RuntimeException('bKash did not return an access token.');
    }
    $expiresIn = max(60, (int) ($response['expires_in'] ?? 3600));
    $save = db()->prepare('INSERT OR REPLACE INTO app_settings (setting_key, setting_value) VALUES (:key, :value)');
    $save->execute(['key' => 'bkash_token', 'value' => $response['id_token']]);
    $save->execute(['key' => 'bkash_token_expiry', 'value' => (string) (time() + $expiresIn)]);

    return $response['id_token'];
}

function bkash_create_payment(array $order, string $callbackState): array
{
    $settings = bkash_configuration();
    $callback = $settings['app_url'] . '/checkout.php?bkash_callback=1&order_id=' . (int) $order['id'] . '&state=' . rawurlencode($callbackState);

    return bkash_request('/payment/create', [
        'payerReference' => substr(preg_replace('/[^A-Za-z0-9]/', '', $order['order_number']) ?: 'ALLSHOP', 0, 30),
        'callbackURL' => $callback,
        'amount' => number_format((int) $order['total'], 2, '.', ''),
        'currency' => 'BDT',
        'intent' => 'sale',
        'merchantInvoiceNumber' => $order['order_number'],
    ]);
}

function bkash_execute_payment(string $paymentId): array
{
    return bkash_request('/payment/execute', ['paymentId' => $paymentId]);
}

function bkash_query_payment(string $paymentId): array
{
    return bkash_request('/query/payment', ['paymentId' => $paymentId]);
}
