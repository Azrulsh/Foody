<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_functions.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}
authRequireUser();
try {
    authCheckCsrf($_POST['csrf_token'] ?? null);
} catch (DomainException $exception) {
    http_response_code(403);
    exit('Your form has expired. Reload the page and try again.');
}
authClearSession();
header('Location: login.php', true, 303);
exit;
