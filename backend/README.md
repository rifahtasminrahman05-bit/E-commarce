# All Shop API

Laravel 13 API foundation for the approved All Shop target architecture. This service currently exposes only the versioned health endpoint and shared request/error response foundations; it does not yet implement commerce, customer authentication, or admin APIs.

## Local development

Requirements: PHP 8.3+, Composer 2, and the PHP extensions required by Laravel. The full development stack uses the root Docker Compose configuration for MySQL 8 and Redis.

From the repository root, follow the target platform setup in `../README.md`. The API health check is `GET /api/v1/health`. Do not expose the admin preview publicly; authentication and authorization are not implemented yet.

To run the current feature tests:

```sh
composer install
php artisan test
```

The test environment uses in-memory SQLite. Do not copy the existing PHP application's SQLite database into this API.
