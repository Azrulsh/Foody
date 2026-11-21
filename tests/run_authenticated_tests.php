<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/auth_functions.php';
$database = serviceDatabase();
$ids = [];
$suffix = bin2hex(random_bytes(12));
$email = 'foody-test-' . $suffix . '@example.invalid';
$otherEmail = 'foody-other-' . $suffix . '@example.invalid';
$password = bin2hex(random_bytes(24));
$failed = false;
try {
    if ($database->query('SELECT DATABASE()')->fetch_row()[0] !== 'foody'
        || !in_array(getenv('FOODY_DB_HOST') ?: 'localhost', ['localhost', '127.0.0.1', '::1'], true)) {
        throw new RuntimeException('HTTP fixtures are restricted to the local foody database.');
    }
    foreach ([$email, $otherEmail] as $fixtureEmail) {
        $name = 'Foody Test User';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $statement = $database->prepare('INSERT INTO auth_users (name, email, password_hash) VALUES (?, ?, ?)');
        $statement->bind_param('sss', $name, $fixtureEmail, $hash);
        $statement->execute();
        $ids[] = (int) $database->insert_id;
        $statement->close();
    }
    $environment = getenv();
    $environment['FOODY_TEST_EMAIL'] = $email;
    $environment['FOODY_TEST_OTHER_EMAIL'] = $otherEmail;
    $environment['FOODY_TEST_PASSWORD'] = $password;
    foreach (['auth_http_test.mjs', 'service_http_test.mjs', 'commission_http_test.mjs'] as $suite) {
        // Credentials travel through the child environment, never command arguments.
        $process = proc_open(['node', __DIR__ . '/' . $suite, 'http://localhost/Foody/'], [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__), $environment);
        if (!is_resource($process) || proc_close($process) !== 0) {
            $failed = true;
            break;
        }
    }
} finally {
    foreach ($ids as $id) {
        $statement = $database->prepare('DELETE FROM auth_users WHERE id = ? AND email IN (?, ?)');
        $statement->bind_param('iss', $id, $email, $otherEmail);
        $statement->execute();
        if ($statement->affected_rows !== 1) { $failed = true; fwrite(STDERR, "Fixture cleanup needs review for ID {$id}." . PHP_EOL); }
        $statement->close();
    }
    $fixtureBuckets = [$email, $otherEmail];
    foreach ($ids as $id) $fixtureBuckets[] = 'profile:' . $id;
    foreach ($fixtureBuckets as $fixtureEmail) {
        $bucket = hash('sha256', 'email:' . $fixtureEmail);
        $statement = $database->prepare('DELETE FROM auth_login_limits WHERE bucket = ?');
        $statement->bind_param('s', $bucket);
        $statement->execute();
        $statement->close();
    }
    $database->close();
}
exit($failed ? 1 : 0);
