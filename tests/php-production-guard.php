<?php
declare(strict_types=1);

$applicationRoot = dirname(__DIR__);
$temporaryDirectory = sys_get_temp_dir() . '/allshop-production-guard-' . bin2hex(random_bytes(8));
if (!mkdir($temporaryDirectory, 0700)) {
    throw new RuntimeException('Could not create the isolated production-guard test directory.');
}

$setConfiguration = static function (array $configuration): void {
    foreach ($configuration as $key => $value) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
};

$setConfiguration([
    'ALLSHOP_ENV' => 'production',
    'ALLSHOP_APP_URL' => 'https://shop.example',
    'ALLSHOP_APP_KEY' => bin2hex(random_bytes(32)),
    'ALLSHOP_ADMIN_USERNAME' => 'release-test-owner',
    'ALLSHOP_ADMIN_PASSWORD' => bin2hex(random_bytes(24)),
    'ALLSHOP_SECURE_COOKIES' => 'true',
    'ALLSHOP_DB_PATH' => $temporaryDirectory . '/store.sqlite',
]);

try {
    require $applicationRoot . '/functions.php';

    if (session_get_cookie_params()['secure'] !== true) {
        throw new RuntimeException('Production sessions must always use secure cookies.');
    }
    if (production_configuration_errors() !== []) {
        throw new RuntimeException('Valid isolated production configuration was rejected.');
    }

    $setConfiguration([
        'ALLSHOP_APP_KEY' => 'replace-with-a-random-secret-at-least-32-characters',
        'ALLSHOP_ADMIN_USERNAME' => 'change-this-admin-name',
        'ALLSHOP_ADMIN_PASSWORD' => 'replace-with-a-random-unique-password-of-20-chars',
        'ALLSHOP_DB_PATH' => $applicationRoot . '/storage/blocked.sqlite',
    ]);

    $errors = production_configuration_errors();
    if (count($errors) < 4) {
        throw new RuntimeException('Production guard did not reject sample credentials and in-tree SQLite storage.');
    }

    fwrite(STDOUT, "Production configuration guard checks passed.\n");
} finally {
    $testDatabase = $temporaryDirectory . '/store.sqlite';
    if (is_file($testDatabase) && !unlink($testDatabase)) {
        throw new RuntimeException('Could not remove the isolated test database.');
    }
    if (!rmdir($temporaryDirectory)) {
        throw new RuntimeException('Could not remove the isolated production-guard test directory.');
    }
}
