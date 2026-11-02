<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($path) || str_contains($path, "\0")) {
    http_response_code(400);
    exit;
}

$decodedPath = rawurldecode($path);
if (
    preg_match('#(?:^|/)\.#', $decodedPath)
    || preg_match('#(?:^|/)(?:backend|docs|frontend|storage|tests)(?:/|$)#i', $decodedPath)
    || preg_match('#\.(?:sqlite(?:-(?:wal|shm))?|db(?:-(?:wal|shm))?|sql|bak|backup|log)$#i', $decodedPath)
    || preg_match('#(?:^|/)(?:README\.md|CHANGELOG\.md|CONTRIBUTING\.md|compose\.ya?ml|Dockerfile|composer\.(?:json|lock)|package(?:-lock)?\.json|phpunit\.xml|phpstan\.neon|router\.php)$#i', $decodedPath)
) {
    http_response_code(404);
    exit;
}

return false;
