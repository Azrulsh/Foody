<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_functions.php';
require_once __DIR__ . '/restaurant_functions.php';
$currentUser = authRequireUser();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed.');
}

$error = '';
$restaurants = [];
$database = null;
$submittedCode = null;
$submittedRevision = null;
$saved = $_SESSION['restaurant_saved'] ?? false;
unset($_SESSION['restaurant_saved']);
try {
    if ($method === 'POST') {
        // Reject invalid tokens and payloads before opening a write connection.
        authCheckCsrf($_POST['csrf_token'] ?? null);
        $submittedCode = restaurantCode($_POST['code'] ?? null);
        $submittedRevision = restaurantRevision($_POST['revision'] ?? null);
        restaurantValidate($_POST);
    }
    $database = serviceDatabase();
    if ($method === 'POST') {
        restaurantUpdate($database, $submittedCode, $_POST, $submittedRevision);
        $_SESSION['restaurant_saved'] = true;
        session_write_close();
        header('Location: my_restaurants.php', true, 303);
        exit;
    }
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    $error = $exception->getMessage();
} catch (DomainException $exception) {
    http_response_code($submittedCode === null ? 403 : 409);
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('Foody restaurant update: ' . $exception->getMessage());
    http_response_code(503);
    $error = 'Restaurant data is unavailable. Your changes have not been saved. Please try again later.';
}

try {
    $database ??= serviceDatabase();
    $restaurants = restaurantLoadAll($database);
    // Keep failed input and its original revision, so stale edits stay protected.
    if ($submittedCode !== null && isset($restaurants[$submittedCode])) {
        foreach (['name' => 400, 'address' => 2000, 'phone' => 120] as $field => $maxBytes) {
            if (is_string($_POST[$field] ?? null) && strlen($_POST[$field]) <= $maxBytes) {
                $restaurants[$submittedCode][$field] = $_POST[$field];
            }
        }
        if ($submittedRevision !== null) {
            $restaurants[$submittedCode]['revision'] = $submittedRevision;
        }
    }
} catch (Throwable $exception) {
    error_log('Foody restaurant read: ' . $exception->getMessage());
    if ($error === '') {
        http_response_code(503);
        $error = 'Restaurant data is unavailable. Please try again later.';
    }
} finally {
    if ($database instanceof mysqli) {
        $database->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Restaurant - Foody</title>
    <link rel="stylesheet" href="Background.css">
    <link rel="stylesheet" href="reportm.css">
    <link rel="stylesheet" href="account.css">
</head>
<body>
    <table border="1" align="center">
        <tr>
            <td rowspan="2" style="width:160px;height:700px"><?php require __DIR__ . '/sidebar.php'; ?></td>
            <td style="width:1000px;text-align:center"><header>MY RESTAURANT</header></td>
        </tr>
        <tr><td class="profile-content">
            <?php if ($error !== ''): ?><p role="alert"><?= serviceEscape($error) ?></p><?php endif; ?>
            <?php if ($saved): ?><p role="status">Restaurant saved.</p><?php endif; ?>
            <?php foreach ($restaurants as $code => $restaurant): ?>
            <form action="my_restaurants.php" method="post" class="profile-form">
                <h2><?= serviceEscape($restaurant['name']) ?></h2>
                <input type="hidden" name="csrf_token" value="<?= serviceEscape(authCsrf()) ?>">
                <input type="hidden" name="code" value="<?= serviceEscape($code) ?>">
                <input type="hidden" name="revision" value="<?= serviceEscape((string) $restaurant['revision']) ?>">
                <label for="<?= serviceEscape($code) ?>-name">Restaurant name</label>
                <input id="<?= serviceEscape($code) ?>-name" name="name" maxlength="100" value="<?= serviceEscape($restaurant['name']) ?>" required>
                <label for="<?= serviceEscape($code) ?>-address">Address</label>
                <textarea id="<?= serviceEscape($code) ?>-address" name="address" maxlength="500" rows="3" autocomplete="street-address" style="box-sizing:border-box;width:100%;padding:8px"><?= serviceEscape($restaurant['address']) ?></textarea>
                <label for="<?= serviceEscape($code) ?>-phone">Phone number</label>
                <input id="<?= serviceEscape($code) ?>-phone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="<?= serviceEscape($restaurant['phone']) ?>">
                <button type="submit">Save</button>
            </form>
            <hr>
            <?php endforeach; ?>
        </td></tr>
    </table>
</body>
</html>
