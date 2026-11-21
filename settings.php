<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_functions.php';
require_once __DIR__ . '/restaurant_functions.php';
require_once __DIR__ . '/settings_functions.php';
require_once __DIR__ . '/appearance_functions.php';
$currentUser = authRequireUser();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed.');
}
$error = '';
$settings = settingsDefaults();
$restaurants = [];
$ready = false;
$csrfVerified = false;
$submittedRevision = null;
$database = null;
$saved = $_SESSION['settings_saved'] ?? '';
unset($_SESSION['settings_saved']);
try {
    if ($method === 'POST') {
        authCheckCsrf($_POST['csrf_token'] ?? null);
        $csrfVerified = true;
        $submittedRevision = settingsRevision($_POST['revision'] ?? null);
        $action = $_POST['action'] ?? null;
        if (!is_string($action) || !in_array($action, ['save', 'reset'], true)) {
            throw new InvalidArgumentException('Invalid settings action.');
        }
        $input = $action === 'reset' ? settingsDefaults() : $_POST;
        if ($action === 'reset') $input['orders_per_page'] = (string) $input['orders_per_page'];
        settingsValidate($input);
        $database = serviceDatabase();
        settingsSave($database, (int) $currentUser['id'], $input, $submittedRevision);
        appearanceRemember($input);
        $_SESSION['settings_saved'] = $action === 'reset' ? 'Settings reset to defaults.' : 'Settings saved.';
        session_write_close();
        header('Location: settings.php', true, 303);
        exit;
    }
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    $error = $exception->getMessage();
} catch (DomainException $exception) {
    http_response_code($csrfVerified ? 409 : 403);
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('Foody settings write: ' . $exception->getMessage());
    http_response_code(503);
    $error = 'Settings could not be saved. Reload the page to check your current settings.';
}
try {
    $database ??= serviceDatabase();
    $settings = settingsLoad($database, (int) $currentUser['id']);
    $restaurants = restaurantLoadAll($database);
    if ($submittedRevision !== null) $settings['revision'] = $submittedRevision;
    // Preserve only allowed submitted choices for correction; never reflect raw values.
    if ($method === 'POST' && $csrfVerified && ($_POST['action'] ?? '') === 'save') {
        foreach (['default_restaurant' => ['', ...SERVICE_RESTAURANTS], 'default_duration' => ['weekly', 'monthly'], 'orders_per_page' => ['10', '25', '50'], 'date_format' => ['d/m/Y', 'Y-m-d'], 'theme' => ['light', 'dark'], 'panel_background' => ['solid', 'transparent']] as $key => $choices) {
            if (is_string($_POST[$key] ?? null) && in_array($_POST[$key], $choices, true)) {
                $settings[$key] = $key === 'orders_per_page' ? (int) $_POST[$key] : $_POST[$key];
            }
        }
    }
    $ready = true;
} catch (Throwable $exception) {
    error_log('Foody settings read: ' . $exception->getMessage());
    if ($error === '') {
        http_response_code(503);
        $error = 'Settings are unavailable. Please try again later.';
    }
} finally {
    if ($database instanceof mysqli) $database->close();
}
require_once __DIR__ . '/appearance_functions.php';
$appearance = appearanceForUser($currentUser);
?>
<!DOCTYPE html>
<html lang="en" <?= appearanceAttributes($appearance) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings - Foody</title>
    <link rel="stylesheet" href="Background.css">
    <link rel="stylesheet" href="reportm.css">
    <link rel="stylesheet" href="account.css">
    <link rel="stylesheet" href="theme.css">
    <script src="settings.js" defer></script>
</head>
<body>
    <table border="1" align="center">
        <tr>
            <td class="sidebar-cell" rowspan="2" style="width:160px;"><?php require __DIR__ . '/sidebar.php'; ?></td>
            <td style="width:1000px;text-align:center"><header>SETTINGS</header></td>
        </tr>
        <tr><td class="profile-content">
            <?php if ($error !== ''): ?><p role="alert"><?= serviceEscape($error) ?></p><?php endif; ?>
            <?php if ($saved !== ''): ?><p role="status"><?= serviceEscape($saved) ?></p><?php endif; ?>
            <p>These preferences apply only to your account. Currency remains MYR (RM).</p>
            <?php if ($ready): ?>
            <form action="settings.php" method="post" class="profile-form" id="settings-form">
                <input type="hidden" name="csrf_token" value="<?= serviceEscape(authCsrf()) ?>">
                <input type="hidden" name="revision" value="<?= serviceEscape((string) $settings['revision']) ?>">
                <label for="default-restaurant">Default restaurant</label>
                <select id="default-restaurant" name="default_restaurant">
                    <option value="" <?= $settings['default_restaurant'] === '' ? 'selected' : '' ?>>All restaurants</option>
                    <?php foreach ($restaurants as $code => $restaurant): ?>
                    <option value="<?= serviceEscape($code) ?>" <?= $settings['default_restaurant'] === $code ? 'selected' : '' ?>><?= serviceEscape($restaurant['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p>Sets the initial Order List filter and new-order restaurant. With All restaurants, new orders initially select the first restaurant. You can override the filter or restaurant when needed.</p>
                <label for="default-duration">Default duration</label>
                <select id="default-duration" name="default_duration">
                    <option value="weekly" <?= $settings['default_duration'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= $settings['default_duration'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                </select>
                <p>Used when opening a restaurant from Main Page.</p>
                <label for="orders-per-page">Orders per page</label>
                <select id="orders-per-page" name="orders_per_page">
                    <?php foreach ([10, 25, 50] as $pageSize): ?>
                    <option value="<?= serviceEscape((string) $pageSize) ?>" <?= $settings['orders_per_page'] === $pageSize ? 'selected' : '' ?>><?= serviceEscape((string) $pageSize) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="date-format">Date format</label>
                <select id="date-format" name="date_format">
                    <option value="d/m/Y" <?= $settings['date_format'] === 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY</option>
                    <option value="Y-m-d" <?= $settings['date_format'] === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                </select>
                <p>Changes displayed order dates. The date input follows your browser's format; stored dates stay the same. My Restaurant continues to show two restaurants per page.</p>
                <label for="theme">Theme</label>
                <select id="theme" name="theme">
                    <option value="light" <?= $settings['theme'] === 'light' ? 'selected' : '' ?>>Light</option>
                    <option value="dark" <?= $settings['theme'] === 'dark' ? 'selected' : '' ?>>Dark</option>
                </select>
                <div id="panel-background-group" <?= $settings['theme'] === 'dark' ? 'hidden' : '' ?>>
                <label for="panel-background">Content background</label>
                <select id="panel-background" name="panel_background">
                    <option value="transparent" <?= $settings['panel_background'] === 'transparent' ? 'selected' : '' ?>>Transparent</option>
                    <option value="solid" <?= $settings['panel_background'] === 'solid' ? 'selected' : '' ?>>Solid</option>
                </select>
                <p>Applies to Light mode on every page. Transparent restores the original semi-transparent appearance, including faded text, buttons and inputs.</p>
                </div>
                <p>Dark mode uses solid panels. Login remembers the last appearance used in this browser.</p>
                <button type="submit" name="action" value="save">Save</button>
                <button type="submit" name="action" value="reset">Reset to Defaults</button>
            </form>
            <?php endif; ?>
        </td></tr>
    </table>
</body>
</html>
