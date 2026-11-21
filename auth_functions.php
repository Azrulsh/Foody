<?php

declare(strict_types=1);

require_once __DIR__ . '/service_functions.php';

const AUTH_DUMMY_HASH = '$2y$12$HT2QfPzLpsuUjDWsQBXAN.dYPBSNbdPfzf0PqSVCe3oYQiDXfYqO2';
const AUTH_IDLE_SECONDS = 1800;
const AUTH_MAX_SECONDS = 43200;

/** CLI-only caller supplies credentials; never create a default password. */
function authCreateInitialUser(mysqli $database, array $input): void
{
    $name = authName($input['name'] ?? null);
    $email = authEmail($input['email'] ?? null);
    $password = authPassword($input['password'] ?? null);
    if (!is_string($input['confirmation'] ?? null) || !hash_equals($password, $input['confirmation'])) {
        throw new InvalidArgumentException('Passwords do not match.');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $locked = (int) $database->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':initial-account'), 10)")->fetch_row()[0] === 1;
    if (!$locked) {
        throw new RuntimeException('Account setup is busy. Try again.');
    }
    try {
        if ((int) $database->query('SELECT COUNT(*) FROM auth_users')->fetch_row()[0] !== 0) {
            throw new DomainException('An account already exists. Initial setup is disabled.');
        }
        $statement = $database->prepare('INSERT INTO auth_users (name, email, password_hash) VALUES (?, ?, ?)');
        try {
            $statement->bind_param('sss', $name, $email, $hash);
            $statement->execute();
        } finally {
            $statement->close();
        }
    } finally {
        $database->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':initial-account'))");
    }
}

function authEmail(mixed $value): string
{
    if (!is_string($value) || strlen($value) > 254 || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }
    return strtolower($value);
}

function authName(mixed $value): string
{
    if (!is_string($value) || !preg_match('/\A[^\p{C}]{1,100}\z/u', trim($value))) {
        throw new InvalidArgumentException('Enter a name between 1 and 100 characters.');
    }
    return trim($value);
}

function authPassword(mixed $value): string
{
    if (!is_string($value) || strlen($value) < 12 || strlen($value) > 72 || str_contains($value, "\0")) {
        throw new InvalidArgumentException('Use a password between 12 and 72 bytes.');
    }
    return $value;
}

function authCsrf(): string
{
    $_SESSION['auth_csrf'] ??= bin2hex(random_bytes(32));
    return $_SESSION['auth_csrf'];
}

function authCheckCsrf(mixed $token): void
{
    if (!is_string($token) || !isset($_SESSION['auth_csrf']) || !hash_equals($_SESSION['auth_csrf'], $token)) {
        throw new DomainException('Your form has expired. Reload the page and try again.');
    }
}

function authClearSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600, 'path' => $cookie['path'],
            'domain' => $cookie['domain'], 'secure' => $cookie['secure'],
            'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

function authEstablish(array $user): void
{
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Could not renew session.');
    }
    $_SESSION = [
        'auth_user_id' => (int) $user['id'],
        'auth_version' => (int) $user['auth_version'],
        'auth_started' => time(), 'auth_seen' => time(),
        'auth_csrf' => bin2hex(random_bytes(32)),
    ];
}

function authLoadUser(mysqli $database, int $id): ?array
{
    $statement = $database->prepare('SELECT id, name, email, auth_version, profile_revision FROM auth_users WHERE id = ?');
    try {
        $statement->bind_param('i', $id);
        $statement->execute();
        return $statement->get_result()->fetch_assoc();
    } finally {
        $statement->close();
    }
}

/** Every protected request checks revocation against the database, before output. */
function authRequireUser(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    serviceStartSession();
    if (!isset($_SESSION['auth_user_id'])
        || time() - ($_SESSION['auth_seen'] ?? 0) > AUTH_IDLE_SECONDS
        || time() - ($_SESSION['auth_started'] ?? 0) > AUTH_MAX_SECONDS) {
        authClearSession();
        header('Location: login.php', true, 303);
        exit;
    }
    $database = null;
    try {
        $database = serviceDatabase();
        $user = authLoadUser($database, (int) $_SESSION['auth_user_id']);
        if ($user === null || (int) $user['auth_version'] !== (int) ($_SESSION['auth_version'] ?? 0)) {
            authClearSession();
            header('Location: login.php', true, 303);
            exit;
        }
        $_SESSION['auth_seen'] = time();
        authCsrf();
        return $cached = $user;
    } catch (Throwable $exception) {
        error_log('Foody authentication: ' . $exception->getMessage());
        http_response_code(503);
        exit('Your account could not be verified. Please try again later.');
    } finally {
        if ($database instanceof mysqli) $database->close();
    }
}

/** Reserve each attempt atomically across sessions and concurrent requests. */
function authReserveAttempt(mysqli $database, string $email, string $ip): bool
{
    $buckets = [hash('sha256', 'email:' . $email) => 5, hash('sha256', 'ip:' . $ip) => 30];
    ksort($buckets);
    $now = time();
    $expires = $now + 900;
    // Bound cleanup work per request; expired counters are no longer effective.
    $cleanup = $database->prepare('DELETE FROM auth_login_limits WHERE expires_at <= ? LIMIT 100');
    $cleanup->bind_param('i', $now);
    $cleanup->execute();
    $cleanup->close();
    $database->begin_transaction();
    try {
        $allowed = true;
        foreach ($buckets as $bucket => $limit) {
            $statement = $database->prepare('INSERT INTO auth_login_limits (bucket, attempts, expires_at) VALUES (?, 0, ?) ON DUPLICATE KEY UPDATE bucket = VALUES(bucket)');
            $statement->bind_param('si', $bucket, $expires);
            $statement->execute();
            $statement->close();
            $statement = $database->prepare('SELECT attempts, expires_at FROM auth_login_limits WHERE bucket = ? FOR UPDATE');
            $statement->bind_param('s', $bucket);
            $statement->execute();
            $row = $statement->get_result()->fetch_assoc();
            $statement->close();
            $attempts = (int) $row['expires_at'] <= $now ? 0 : (int) $row['attempts'];
            $window = (int) $row['expires_at'] <= $now ? $expires : (int) $row['expires_at'];
            if ($attempts >= $limit) $allowed = false;
            $attempts = min($attempts + 1, $limit);
            $statement = $database->prepare('UPDATE auth_login_limits SET attempts = ?, expires_at = ? WHERE bucket = ?');
            $statement->bind_param('iis', $attempts, $window, $bucket);
            $statement->execute();
            $statement->close();
        }
        $database->commit();
        return $allowed;
    } catch (Throwable $exception) {
        $database->rollback();
        throw $exception;
    }
}

function authLogin(mysqli $database, string $email, string $password, string $ip): ?array
{
    if (!authReserveAttempt($database, $email, $ip)) {
        throw new OverflowException('Too many login attempts. Please try again in 15 minutes.');
    }
    $statement = $database->prepare('SELECT id, password_hash, auth_version FROM auth_users WHERE email = ?');
    try {
        $statement->bind_param('s', $email);
        $statement->execute();
        $user = $statement->get_result()->fetch_assoc();
    } finally {
        $statement->close();
    }
    $valid = password_verify($password, $user['password_hash'] ?? AUTH_DUMMY_HASH);
    if (!$valid || $user === null) return null;
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $statement = $database->prepare('UPDATE auth_users SET password_hash = ? WHERE id = ? AND auth_version = ? AND password_hash = ?');
        $statement->bind_param('siis', $newHash, $user['id'], $user['auth_version'], $user['password_hash']);
        $statement->execute();
        $statement->close();
    }
    $bucket = hash('sha256', 'email:' . $email);
    $statement = $database->prepare('DELETE FROM auth_login_limits WHERE bucket = ?');
    $statement->bind_param('s', $bucket);
    $statement->execute();
    $statement->close();
    // The IP counter remains intact even after a successful login.
    return $user;
}

/** Optimistic update prevents stale profile forms and concurrent password changes. */
function authUpdateProfile(mysqli $database, array $user, array $input, int $revision): bool
{
    $name = authName($input['name'] ?? null);
    $email = authEmail($input['email'] ?? null);
    $newPassword = $input['new_password'] ?? '';
    $confirmation = $input['password_confirmation'] ?? '';
    if (!is_string($newPassword) || !is_string($confirmation)) {
        throw new InvalidArgumentException('Invalid password fields.');
    }
    $sensitive = $email !== $user['email'] || $newPassword !== '';
    $statement = $database->prepare('SELECT password_hash FROM auth_users WHERE id = ?');
    $statement->bind_param('i', $user['id']);
    $statement->execute();
    $stored = $statement->get_result()->fetch_assoc();
    $statement->close();
    if ($stored === null) throw new DomainException('Your account is no longer available.');
    if ($sensitive) {
        $current = $input['current_password'] ?? null;
        if (!is_string($current) || strlen($current) > 72 || !password_verify($current, $stored['password_hash'])) {
            throw new InvalidArgumentException('Enter your current password to change your email or password.');
        }
    }
    $hash = $stored['password_hash'];
    if ($newPassword !== '') {
        authPassword($newPassword);
        if (!hash_equals($newPassword, $confirmation)) {
            throw new InvalidArgumentException('New passwords do not match.');
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    } elseif ($confirmation !== '') {
        throw new InvalidArgumentException('Enter the new password and its confirmation.');
    }
    $versionIncrement = $sensitive ? 1 : 0;
    $statement = $database->prepare('UPDATE auth_users SET name = ?, email = ?, password_hash = ?, auth_version = auth_version + ?, profile_revision = profile_revision + 1 WHERE id = ? AND profile_revision = ? AND auth_version = ?');
    try {
        $statement->bind_param('sssiiii', $name, $email, $hash, $versionIncrement, $user['id'], $revision, $user['auth_version']);
        $statement->execute();
        if ($statement->affected_rows !== 1) throw new DomainException('Your profile has changed. Reload the page before saving again.');
    } catch (mysqli_sql_exception $exception) {
        if ($exception->getCode() === 1062) throw new InvalidArgumentException('That email address is unavailable.');
        throw $exception;
    } finally {
        $statement->close();
    }
    return $sensitive;
}
