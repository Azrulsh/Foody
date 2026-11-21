<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_functions.php';
$currentUser = authRequireUser();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed.');
}
$error = '';
$name = $currentUser['name'];
$email = $currentUser['email'];
$revision = (string) $currentUser['profile_revision'];
$saved = $_SESSION['profile_saved'] ?? false;
unset($_SESSION['profile_saved']);
$database = null;
if ($method === 'POST') {
    try {
        authCheckCsrf($_POST['csrf_token'] ?? null);
        $name = authName($_POST['name'] ?? null);
        $email = authEmail($_POST['email'] ?? null);
        $revision = $_POST['revision'] ?? null;
        if (!is_string($revision) || !preg_match('/\A[1-9][0-9]{0,14}\z/D', $revision)) {
            throw new InvalidArgumentException('Invalid profile version. Reload this page.');
        }
        $database = serviceDatabase();
        if (($email !== $currentUser['email'] || ($_POST['new_password'] ?? '') !== '')
            && !authReserveAttempt($database, 'profile:' . $currentUser['id'], 'profile:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))) {
            throw new OverflowException('Too many attempts. Please try again in 15 minutes.');
        }
        $sensitive = authUpdateProfile($database, $currentUser, $_POST, (int) $revision);
        if ($sensitive) {
            // Revoke all older sessions; keep this browser signed in with a fresh ID.
            $updated = authLoadUser($database, (int) $currentUser['id']);
            authEstablish($updated);
        }
        $_SESSION['profile_saved'] = true;
        session_write_close();
        header('Location: profile.php', true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    } catch (DomainException $exception) {
        http_response_code(str_starts_with($exception->getMessage(), 'Your form') ? 403 : 409);
        $error = $exception->getMessage();
    } catch (OverflowException $exception) {
        http_response_code(429);
        header('Retry-After: 900');
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('Foody profile: ' . $exception->getMessage());
        http_response_code(503);
        $error = 'Your profile could not be saved. Please try again later.';
    } finally {
        if ($database instanceof mysqli) $database->close();
    }
}
$revision = is_string($revision) ? $revision : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile - Foody</title>
    <link rel="stylesheet" href="Background.css">
    <link rel="stylesheet" href="reportm.css">
    <link rel="stylesheet" href="account.css">
</head>
<body>
    <table border="1" align="center">
        <tr>
            <td rowspan="2" style="width:160px;height:700px"><?php require __DIR__ . '/sidebar.php'; ?></td>
            <td style="width:1000px;text-align:center"><header>MY PROFILE</header></td>
        </tr>
        <tr><td class="profile-content">
            <?php if ($error !== ''): ?><p role="alert"><?= serviceEscape($error) ?></p><?php endif; ?>
            <?php if ($saved): ?><p role="status">Profile saved.</p><?php endif; ?>
            <form action="profile.php" method="post" class="profile-form">
                <input type="hidden" name="csrf_token" value="<?= serviceEscape(authCsrf()) ?>">
                <input type="hidden" name="revision" value="<?= serviceEscape($revision) ?>">
                <label for="name">Name</label>
                <input id="name" name="name" maxlength="100" autocomplete="name" value="<?= serviceEscape($name) ?>" required>
                <label for="email">Email</label>
                <input id="email" name="email" type="email" maxlength="254" autocomplete="username" value="<?= serviceEscape($email) ?>" required>
                <label for="current-password">Current password (required to change email or password)</label>
                <input id="current-password" name="current_password" type="password" maxlength="72" autocomplete="current-password">
                <label for="new-password">New password (leave blank to keep current password)</label>
                <input id="new-password" name="new_password" type="password" minlength="12" maxlength="72" autocomplete="new-password">
                <label for="password-confirmation">Confirm new password</label>
                <input id="password-confirmation" name="password_confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password">
                <button type="submit">Save</button>
            </form>
        </td></tr>
    </table>
</body>
</html>
