<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_functions.php';
$currentUser = authRequireUser();
try {
    $conn = serviceDatabase();
} catch (Throwable $exception) {
    error_log('Foody database: ' . $exception->getMessage());
    http_response_code(503);
    exit('Database access is unavailable. Please try again later.');
}