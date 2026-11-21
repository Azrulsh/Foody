<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_functions.php';
require_once __DIR__ . '/appearance_functions.php';
serviceStartSession();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed.');
}
if (isset($_SESSION['auth_user_id'])) {
    authRequireUser();
    header('Location: main.php', true, 303);
    exit;
}
$error = '';
$email = '';
$database = null;
if ($method === 'POST') {
    try {
        authCheckCsrf($_POST['csrf_token'] ?? null);
        $email = authEmail($_POST['email'] ?? null);
        $password = $_POST['password'] ?? null;
        if (!is_string($password) || $password === '' || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new InvalidArgumentException('Enter your email and password.');
        }
        $database = serviceDatabase();
        $user = authLogin($database, $email, $password, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if ($user === null) {
            http_response_code(401);
            $error = 'Email or password is incorrect.';
        } else {
            authEstablish($user);
            appearanceForUser($user);
            session_write_close();
            header('Location: main.php', true, 303);
            exit;
        }
    } catch (DomainException $exception) {
        http_response_code(403);
        $error = $exception->getMessage();
    } catch (InvalidArgumentException $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    } catch (OverflowException $exception) {
        http_response_code(429);
        header('Retry-After: 900');
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('Foody login: ' . $exception->getMessage());
        http_response_code(503);
        $error = 'Login is unavailable. Please try again later.';
    } finally {
        if ($database instanceof mysqli) $database->close();
    }
}
$token = authCsrf();
session_write_close();
require_once __DIR__ . '/appearance_functions.php';
$appearance = appearanceBrowser();
?>
<!DOCTYPE html>
<html lang="en" <?= appearanceAttributes($appearance) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Foody</title>
    <link rel="stylesheet" href="Background.css">
    <link rel="stylesheet" href="reportm.css">
    <link rel="stylesheet" href="account.css">
    <link rel="stylesheet" href="theme.css">
</head>
<body>
    <main class="login-box">
        <img src="Logo.png" alt="Foody logo">
        <header>LOGIN</header>
        <?php if ($error !== ''): ?><p role="alert"><?= serviceEscape($error) ?></p><?php endif; ?>
        <form action="login.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= serviceEscape($token) ?>">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="254" autocomplete="username" value="<?= serviceEscape($email) ?>" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" maxlength="72" autocomplete="current-password" required>
            <button type="submit">Login</button>
        </form>
    </main>
</body>
</html>
