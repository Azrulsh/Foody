<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_functions.php';
require_once __DIR__ . '/order_functions.php';
if (!defined('FOODY_ORDER_PAGE')) {
    http_response_code(404);
    exit;
}
$currentUser = authRequireUser();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed.');
}
$error = '';
$filter = '';
$page = 1;
$restaurants = [];
$listing = ['rows' => [], 'page' => 1, 'pages' => 1, 'count' => 0];
$ready = false;
$database = null;
$csrfVerified = false;
$values = [
    'restaurant' => 'jamilah',
    'order_date' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))->format('Y-m-d'),
    'customer_name' => '', 'total' => '0',
];
$saved = $_SESSION['order_saved'] ?? '';
unset($_SESSION['order_saved']);
$_SESSION['order_forms'] ??= [];
foreach ($_SESSION['order_forms'] as $token => $expiry) {
    if ($expiry < time()) unset($_SESSION['order_forms'][$token]);
}
$orderRequestKey = '';
try {
    if (isset($_GET['restaurant']) && $_GET['restaurant'] !== '') {
        $filter = restaurantCode($_GET['restaurant']);
        $values['restaurant'] = $filter;
    }
    if (isset($_GET['page'])) $page = orderPositiveInteger($_GET['page'], 'page number');
    if ($method === 'POST') {
        authCheckCsrf($_POST['csrf_token'] ?? null);
        $csrfVerified = true;
        $action = $_POST['action'] ?? null;
        if (!is_string($action) || !in_array($action, ['create', 'status'], true)) {
            throw new InvalidArgumentException('Invalid order action.');
        }
        if ($action === 'create') {
            foreach ($values as $field => $value) {
                if (is_string($_POST[$field] ?? null) && strlen($_POST[$field]) <= 400) $values[$field] = $_POST[$field];
            }
            $token = $_POST['request_key'] ?? null;
            if (!is_string($token) || !isset($_SESSION['order_forms'][$token])) {
                throw new DomainException('This order form expired or was already submitted. Reload the list before trying again.');
            }
            $orderRequestKey = $token;
            orderValidate($_POST);
        } else {
            $id = orderPositiveInteger($_POST['id'] ?? null, 'order number');
            $revision = orderPositiveInteger($_POST['revision'] ?? null, 'order version');
            $status = $_POST['status'] ?? null;
            if (!is_string($status) || !in_array($status, ['completed', 'cancelled'], true)) {
                throw new InvalidArgumentException('Choose Completed or Cancelled for a pending order.');
            }
        }
        $database = serviceDatabase();
        if ($action === 'create') {
            $id = orderCreate($database, $_POST, (int) $currentUser['id'], $orderRequestKey);
            unset($_SESSION['order_forms'][$orderRequestKey]);
            $_SESSION['order_saved'] = 'Order ' . orderNumber($id) . ' added.';
        } else {
            orderSetStatus($database, $id, $revision, $status, (int) $currentUser['id']);
            $_SESSION['order_saved'] = 'Order status updated.';
        }
        session_write_close();
        header('Location: orders.php' . ($filter !== '' ? '?restaurant=' . rawurlencode($filter) : ''), true, 303);
        exit;
    }
} catch (InvalidArgumentException $exception) {
    http_response_code($method === 'POST' ? 422 : 400);
    $error = $exception->getMessage();
} catch (DomainException $exception) {
    http_response_code($csrfVerified ? 409 : 403);
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('Foody order write: ' . $exception->getMessage());
    http_response_code(503);
    $error = 'Order data is unavailable. Reopen the list to check saved orders before trying again.';
}
try {
    $database ??= serviceDatabase();
    $restaurants = restaurantLoadAll($database);
    $listing = orderList($database, $filter, $page);
    $ready = true;
} catch (Throwable $exception) {
    error_log('Foody order read: ' . $exception->getMessage());
    if ($error === '') {
        http_response_code(503);
        $error = 'Order data is unavailable. Please try again later.';
    }
} finally {
    if ($database instanceof mysqli) $database->close();
}
if ($orderRequestKey === '') {
    while (count($_SESSION['order_forms']) >= 30) array_shift($_SESSION['order_forms']);
    $orderRequestKey = bin2hex(random_bytes(32));
    $_SESSION['order_forms'][$orderRequestKey] = time() + 3600;
}
$orderActionUrl = 'orders.php' . ($filter !== '' ? '?restaurant=' . rawurlencode($filter) : '');
