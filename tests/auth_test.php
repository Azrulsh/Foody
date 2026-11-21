<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/auth_functions.php';
$checks = 0;
function authTest(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function authReject(callable $operation, string $type): void {
    try { $operation(); } catch (Throwable $exception) {
        authTest($exception instanceof $type, 'Expected ' . $type);
        return;
    }
    authTest(false, 'Expected rejection.');
}
authTest(authEmail('Person@Example.com') === 'person@example.com', 'Normalize email.');
authTest(authName('  Test User  ') === 'Test User', 'Trim name.');
foreach ([[], 'bad', 'a@b', str_repeat('a', 255)] as $invalid) authReject(fn () => authEmail($invalid), InvalidArgumentException::class);
foreach (['', [], "test\nname", str_repeat('a', 101)] as $invalid) authReject(fn () => authName($invalid), InvalidArgumentException::class);
foreach (['short', [], str_repeat('a', 73), "123456789012\0"] as $invalid) authReject(fn () => authPassword($invalid), InvalidArgumentException::class);
authTest(authPassword('a valid passphrase 123') === 'a valid passphrase 123', 'Accept passphrase.');
$database = serviceDatabase();
try {
    foreach (['auth_users', 'auth_login_limits'] as $table) {
        $definition = $database->query("SHOW CREATE TABLE {$table}")->fetch_row()[1];
        $database->query(str_replace("CREATE TABLE `{$table}`", "CREATE TEMPORARY TABLE `{$table}`", $definition));
    }
    $email = 'test@example.invalid';
    $name = 'Test User';
    $password = 'test passphrase 12345';
    $setup = ['name' => $name, 'email' => $email, 'password' => $password, 'confirmation' => $password];
    authCreateInitialUser($database, $setup);
    $stored = $database->query('SELECT id, password_hash FROM auth_users')->fetch_assoc();
    $id = (int) $stored['id'];
    authTest($stored['password_hash'] !== $password && password_verify($password, $stored['password_hash']), 'Initial setup hashes password.');
    authReject(fn () => authCreateInitialUser($database, $setup), DomainException::class);
    authTest(authLogin($database, $email, 'incorrect', 'ip-a') === null, 'Wrong password.');
    $loggedIn = authLogin($database, $email, $password, 'ip-a');
    authTest((int) $loggedIn['id'] === $id, 'Successful login.');
    $user = authLoadUser($database, $id);
    $profile = ['name' => 'Changed', 'email' => $email];
    authTest(authUpdateProfile($database, $user, $profile, 1) === false, 'Name change preserves sessions.');
    authReject(fn () => authUpdateProfile($database, $user, $profile, 1), DomainException::class);
    $user = authLoadUser($database, $id);
    $profile['email'] = 'new@example.invalid';
    authReject(fn () => authUpdateProfile($database, $user, $profile, 2), InvalidArgumentException::class);
    $profile['current_password'] = $password;
    $profile['new_password'] = 'new test passphrase 123';
    $profile['password_confirmation'] = 'different';
    authReject(fn () => authUpdateProfile($database, $user, $profile, 2), InvalidArgumentException::class);
    $profile['password_confirmation'] = $profile['new_password'];
    authTest(authUpdateProfile($database, $user, $profile, 2) === true, 'Sensitive change.');
    $changed = authLoadUser($database, $id);
    authTest((int) $changed['auth_version'] === 2 && (int) $changed['profile_revision'] === 3, 'Revoke older sessions and advance revision.');
    authTest(authLogin($database, $profile['email'], $password, 'ip-b') === null, 'Old password no longer works.');
    authTest(authLogin($database, $profile['email'], $profile['new_password'], 'ip-b') !== null, 'New password works.');
    for ($attempt = 0; $attempt < 5; $attempt++) authTest(authReserveAttempt($database, 'limited', 'ip-c'), 'First five account attempts.');
    authTest(!authReserveAttempt($database, 'limited', 'new-ip'), 'Account limit persists across IPs.');
    for ($attempt = 0; $attempt < 30; $attempt++) authTest(authReserveAttempt($database, 'different-' . $attempt, 'ip-limit'), 'IP budget.');
    authTest(!authReserveAttempt($database, 'another-account', 'ip-limit'), 'IP limit persists across accounts.');
    $database->query('UPDATE auth_login_limits SET expires_at = 0');
    authTest(authReserveAttempt($database, 'limited', 'new-ip'), 'Expired limits reset.');
} finally { $database->close(); }
echo "Passed {$checks} authentication checks." . PHP_EOL;
